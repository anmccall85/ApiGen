<?php declare(strict_types=1);

namespace ApiGen\Parser\Reflection;

use ApiGen\Contracts\Parser\Reflection\Behavior\InClassInterface;
use ApiGen\Contracts\Parser\Reflection\ElementReflectionInterface;
use TokenReflection;
use TokenReflection\Exception\BaseException;
use TokenReflection\ReflectionAnnotation;
use TokenReflection\ReflectionClass;
use TokenReflection\ReflectionConstant;
use TokenReflection\ReflectionFunction;

abstract class ReflectionElement extends ReflectionBase implements ElementReflectionInterface
{

    /**
     * @var bool
     */
    protected $isDocumented;

    /**
     * @var array
     */
    protected $annotations;


    public function getExtension(): ?ReflectionExtension
    {
        $extension = $this->reflection->getExtension();
        return $extension === null ? null : $this->reflectionFactory->createFromReflection($extension);
    }


    public function getExtensionName(): string
    {
        return $this->reflection->getExtensionName();
    }


    public function getStartPosition(): int
    {
        return $this->reflection->getStartPosition();
    }


    public function getEndPosition(): int
    {
        return $this->reflection->getEndPosition();
    }


    public function isMain(): bool
    {
        $main = $this->configuration->getMain();
        return empty($main) || strpos($this->getName(), $main) === 0;
    }


    public function isDocumented(): bool
    {
        if ($this->isDocumented === null) {
            $this->isDocumented = $this->reflection->isTokenized() || $this->reflection->isInternal();

            if ($this->isDocumented) {
                $internal = $this->configuration->isInternalDocumented();

                if ($this->reflection->isInternal()) {
                    $this->isDocumented = false;
                } elseif (! $internal && $this->reflection->hasAnnotation('internal')) {
                    $this->isDocumented = false;
                } elseif ($this->reflection->hasAnnotation('ignore')) {
                    $this->isDocumented = false;
                }
            }
        }

        return $this->isDocumented;
    }


    public function isDeprecated(): bool
    {
        if ($this->reflection->isDeprecated()) {
            return true;
        }

        if ($this instanceof InClassInterface) {
            $class = $this->getDeclaringClass();
            return !is_null($class) && $class->isDeprecated();
        }

        return false;
    }


    /**
     * Removed, but for BC in templates.
     */
    public function inPackage(): bool
    {
        return false;
    }


    /**
     * Removed, but for BC in templates.
     */
    public function inNamespace(): bool
    {
        return true;
    }


    public function getNamespaceName(): string
    {
        static $namespaces = [];

        $namespaceName = $this->reflection->getNamespaceName();

        if (! $namespaceName) {
            return $namespaceName;
        }

        $lowerNamespaceName = strtolower($namespaceName);
        if (! isset($namespaces[$lowerNamespaceName])) {
            $namespaces[$lowerNamespaceName] = $namespaceName;
        }

        return $namespaces[$lowerNamespaceName];
    }


    public function getPseudoNamespaceName(): string
    {
        return $this->isInternal() ? 'PHP' : $this->getNamespaceName() ?: 'None';
    }


    public function getNamespaceAliases(): array
    {
        return $this->reflection->getNamespaceAliases();
    }


    public function getShortDescription(): string
    {
        $short = $this->reflection->getAnnotation(ReflectionAnnotation::SHORT_DESCRIPTION);
        if (! empty($short)) {
            return $short;
        }

        if ($this instanceof ReflectionProperty || $this instanceof ReflectionConstant) {
            $var = $this->getAnnotation('var');
            [, $short] = preg_split('~\s+|$~', $var[0], 2);
        }

        return (string) $short;
    }


    public function getLongDescription(): string
    {
        $short = $this->getShortDescription();
        $long = $this->reflection->getAnnotation(ReflectionAnnotation::LONG_DESCRIPTION);

        if (! empty($long)) {
            $short .= "\n\n" . $long;
        }

        return $short;
    }


    public function getDocComment(): string
    {
        return (string) $this->reflection->getDocComment();
    }


    public function getAnnotations(): array
    {
        if ($this->annotations === null) {
            $annotations = $this->reflection->getAnnotations();
            $annotations = array_change_key_case($annotations, CASE_LOWER);

            unset($annotations[ReflectionAnnotation::SHORT_DESCRIPTION]);
            unset($annotations[ReflectionAnnotation::LONG_DESCRIPTION]);

            $annotations += $this->getAnnotationsFromReflection($this->reflection);
            $this->annotations = $annotations;
        }

        return $this->annotations;
    }


    public function getAnnotation(string $name): array
    {
        return $this->hasAnnotation($name) ? $this->getAnnotations()[$name] : [];
    }


    public function hasAnnotation(string $name): bool
    {
        return isset($this->getAnnotations()[$name]);
    }


    /**
     * @param string $annotation
     * @param mixed $value
     */
    public function addAnnotation(string $annotation, $value): void
    {
        if ($this->annotations === null) {
            $this->getAnnotations();
        }
        $this->annotations[$annotation][] = $value;
    }


    /**
     * @param mixed $reflection
     */
    private function getAnnotationsFromReflection($reflection): array
    {
        $fileLevel = [
            'package' => true,
            'subpackage' => true,
            'author' => true,
            'license' => true,
            'copyright' => true
        ];

        $annotations = [];
        if ($reflection instanceof ReflectionClass || $reflection instanceof ReflectionFunction
            || ($reflection instanceof ReflectionConstant  && $reflection->getDeclaringClassName() === null)
        ) {
            foreach ($reflection->getFileReflection()->getAnnotations() as $name => $value) {
                if (isset($fileLevel[$name]) && empty($annotations[$name])) {
                    $annotations[$name] = $value;
                }
            }
        }
        return $annotations;
    }
}
