<?php

declare(strict_types=1);

namespace EonX\EasyStandard\Rector;

use Nette\Utils\Strings;
use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocChildNode;
use Rector\AttributeAwarePhpDoc\Ast\PhpDoc\AttributeAwareGenericTagValueNode;
use Rector\AttributeAwarePhpDoc\Ast\PhpDoc\AttributeAwarePhpDocTagNode;
use Rector\AttributeAwarePhpDoc\Ast\PhpDoc\AttributeAwarePhpDocTextNode;
use Rector\AttributeAwarePhpDoc\Ast\PhpDoc\AttributeAwareVarTagValueNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\RectorDefinition\CodeSample;
use Rector\Core\RectorDefinition\RectorDefinition;
use Rector\NodeTypeResolver\Node\AttributeKey;

/**
 * @see \EonX\EasyStandard\Tests\Rector\PhpDocCommentRector\PhpDocCommentRectorTest
 */
final class PhpDocCommentRector extends AbstractRector
{
    /**
     * @var string[]
     */
    public $allowedEnd = ['.', ',', '?', '!', ':', ')', '(', '}', '{', ']', '['];

    /**
     * @var bool
     */
    private $isMultilineTagNode;

    /**
     * From this method documentation is generated.
     */
    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Corrects comments in annotations',
            [
                new CodeSample(
                    <<<'PHP'
/**
 * Some class
 */
class SomeClass
{
}
PHP
                    ,
                    <<<'PHP'
/**
 * Some class.
*/
class SomeClass
{
}
PHP
                ),
            ]
        );
    }

    public function getNodeTypes(): array
    {
        return [Node::class];
    }

    public function refactor(Node $node): ?Node
    {
        if ($node->hasAttribute(AttributeKey::PHP_DOC_INFO)) {
            $this->checkPhpDoc($node->getAttribute(AttributeKey::PHP_DOC_INFO));
        }

        return $node;
    }

    private function checkGenericTagValueNode(AttributeAwarePhpDocTagNode $attributeAwarePhpDocTagNode): void
    {
        /** @var GenericTagValueNode $value */
        $value = $attributeAwarePhpDocTagNode->value;

        if (Strings::startsWith($attributeAwarePhpDocTagNode->name, '@')) {
            $this->isMultilineTagNode = true;

            if (Strings::endsWith($value->value, ')')) {
                $this->isMultilineTagNode = false;
            }

            $firstValueLetter = Strings::substring($value->value, 0, 1);

            if (\in_array($firstValueLetter, ['\\', '('], true) === false) {
                $attributeAwarePhpDocTagNode->name .= ' ';
            }

            $valueAsArray = (array)\explode(')', $value->value);

            if (\count($valueAsArray) === 2) {
                if ($this->isLineEndingWithAllowed($valueAsArray[1])) {
                    $valueAsArray[1] = Strings::substring($valueAsArray[1], 0, -1);
                }

                $valueAsArray[1] = Strings::firstLower(Strings::trim($valueAsArray[1]));

                $value->value = implode(') ', $valueAsArray);
            }

            return;
        }

        if ($value->value === '') {
            return;
        }

        if ($this->isLineEndingWithAllowed($value->value)) {
            return;
        }

        $value->value = Strings::substring($value->value, 0, -1);
    }

    private function checkPhpDoc(PhpDocInfo $phpDocInfo): void
    {
        $children = $phpDocInfo->getPhpDocNode()
            ->children;

        foreach ($children as $phpDocChildNode) {
            /** @var PhpDocChildNode $phpDocChildNode */
            $content = (string)$phpDocChildNode;
            if (Strings::match($content, '#inheritdoc#i')) {
                continue;
            }

            if ($phpDocChildNode instanceof AttributeAwarePhpDocTextNode) {
                if ($this->isMultilineTagNode) {
                    if (Strings::endsWith($phpDocChildNode->text, ')')) {
                        $this->isMultilineTagNode = false;
                    }

                    continue;
                }

                $this->checkTextNode($phpDocChildNode);

                continue;
            }

            if ($phpDocChildNode instanceof AttributeAwarePhpDocTagNode) {
                $this->checkTagNode($phpDocChildNode);

                continue;
            }
        }

        $this->isMultilineTagNode = false;
    }

    private function checkTagNode(AttributeAwarePhpDocTagNode $attributeAwarePhpDocTagNode): void
    {
        if ($attributeAwarePhpDocTagNode->value instanceof AttributeAwareGenericTagValueNode) {
            $this->checkGenericTagValueNode($attributeAwarePhpDocTagNode);
        }

        if ($attributeAwarePhpDocTagNode->value instanceof AttributeAwareVarTagValueNode) {
            $this->checkVarTagValueNode($attributeAwarePhpDocTagNode);
        }
    }

    private function checkTextNode(AttributeAwarePhpDocTextNode $attributeAwarePhpDocTextNode): void
    {
        if ($attributeAwarePhpDocTextNode->text === '') {
            $this->isMultilineTagNode = false;

            return;
        }

        $text = (array)\explode(PHP_EOL, $attributeAwarePhpDocTextNode->text);
        $firstKey = array_key_first($text);
        $lastKey = array_key_last($text);

        foreach ($text as $index => $value) {
            $text[$index] = Strings::trim($value);
        }

        $text[$firstKey] = Strings::firstUpper($text[$firstKey]);

        if ($this->isLineEndingWithAllowed($text[$lastKey]) === false) {
            $text[$lastKey] .= '.';
        }

        $attributeAwarePhpDocTextNode->text = \implode(PHP_EOL, $text);
        $attributeAwarePhpDocTextNode->setAttribute('original_content', '');
    }

    private function checkVarTagValueNode(AttributeAwarePhpDocTagNode $attributeAwarePhpDocTagNode): void
    {
        /** @var AttributeAwareVarTagValueNode $varTagValueNode */
        $varTagValueNode = $attributeAwarePhpDocTagNode->value;

        if ($varTagValueNode->description === '' || $varTagValueNode->variableName === '') {
            return;
        }

        $varTagValueNode->description = Strings::firstLower(\trim($varTagValueNode->description));

        if ($this->isLineEndingWithAllowed($varTagValueNode->description)) {
            $varTagValueNode->description = Strings::substring($varTagValueNode->description, 0, -1);
        }
    }

    private function isLineEndingWithAllowed(string $docLineContent): bool
    {
        $lastCharacter = Strings::substring($docLineContent, -1);

        return \in_array($lastCharacter, $this->allowedEnd, true);
    }
}
