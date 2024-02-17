<?php declare(strict_types=1);

namespace Codelicia\Psalm\Sniffs;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\AnnotationHelper;
use SlevomatCodingStandard\Helpers\FixerHelper;
use SlevomatCodingStandard\Helpers\Annotation;
use function array_map;
use function array_pop;
use function array_shift;
use function explode;
use function implode;
use function preg_match_all;
use function preg_replace;
use function str_contains;
use function str_repeat;
use function str_starts_with;
use function strlen;
use const PREG_SPLIT_DELIM_CAPTURE;
use const T_DOC_COMMENT_OPEN_TAG;

final class ArrayShapeSniff implements Sniff
{
    private const LINE_LIMIT = 120;

    public function register(): array
    {
        return [
            T_DOC_COMMENT_OPEN_TAG,
        ];
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        /** @var Annotation[] $annotations */
        $annotations = AnnotationHelper::getAnnotations($phpcsFile, $stackPtr, '@psalm-return');

        foreach ($annotations as $annotation) {

            // @todo(malukenho): only format lines bigger then {@see self::LINE_LIMIT}
            // @todo(malukenho): can we get only the named groups
            if ($annotation->getValue() === null) {
                continue;
            }

            $value = (string) $annotation->getValue();

            if (! str_starts_with($value, 'array{')) {
                continue;
            }

            if (strlen($value) < self::LINE_LIMIT) {
                continue;
            }

            if (str_contains($value, "\n")) {
                continue;
            }

            // @todo(malukenho): save extra data to append it later
            $solo = preg_replace('/(\$[\w_0-9]+.+)/', '', $value);

            if (empty($solo)) {
                continue;
            }

            preg_match_all('/(?<literal>[\w?.:\'\"\-]+)?(?<divider>[{}\(\)\|<>:\[\],])/', $solo, $matches, PREG_SPLIT_DELIM_CAPTURE);
            $indentLevel = 0;
            $quantity = 2;
            $doc = '';

            // @todo(malukenho): can we do it with {@see array_reduce}?
            $previousDivier = '';
            foreach ($matches as $match) {
                $identation = str_repeat(' ', $indentLevel * $quantity);

                $literal = $match['literal'];
                $divider = $match['divider'];

                // We should always print the literal first
                if ($divider !== ':') {
                    $doc .= $literal;
                }

                if ($divider === '{') {
                    $doc .= $divider . "\n";
                    $indentLevel++;
                }

                if ($divider === ':') {
                    $doc .= $identation . $literal . $divider . ' ';
                }

                if ($divider === ',') {
                    $doc .= $divider . "\n";
                }

                $doc .= match ($divider) {
                    '<', '>', '|', '(', ')' => $divider,
                    default => ''
                };

                if ($divider === '}') {
                    $indentLevel--;
                    $identation = str_repeat(' ', $indentLevel * $quantity);
                    $doc .= ($previousDivier === ',' ?  '' : ',') . "\n" . $identation . $divider;
                }

                $previousDivier = $match['divider'];
            }

            $lines = explode("\n", $doc);
            $firstLine = array_shift($lines);

            $printer = array_map(static fn (string $line) => '     * ' . $line, $lines);

            // Notify error
            $current  = explode("\n", $value);
            $start = array_shift($current);
            $end = array_pop($current);

            $formated = $start . "\n" . implode("\n", array_map(static fn (string $l): string => '  ' . $l, $current)) . "\n" . $end;

            if ($formated !== $doc) {
                $fix = $phpcsFile->addFixableError('Psalm tag not formatted.', $annotation->getStartPointer(), 'a');

                if ($fix) {
                    $phpcsFile->fixer->beginChangeset();
                    $docCommentStartPointer = $annotation->getStartPointer();

                    FixerHelper::removeBetweenIncluding($phpcsFile, $annotation->getStartPointer(), $annotation->getEndPointer());
                    $phpcsFile->fixer->addContent($docCommentStartPointer, $annotation->getName() . ' ' . $firstLine);

                    foreach ($printer as $line) {
                        $phpcsFile->fixer->addNewline($docCommentStartPointer);
                        $phpcsFile->fixer->addContent($docCommentStartPointer, $line);
                    }

                    $phpcsFile->fixer->endChangeset();
                }
            }
        }
    }
}