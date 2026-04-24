<?php

declare(strict_types=1);

namespace BobKosse\DataSecurity\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Finder\SplFileInfo;

class ModelHandlingHelper
{
    public function getModels(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        $models = [];

        foreach (File::allFiles($path) as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->getClassNameFromFile($file->getRealPath() ?: $file->getPathname());

            if ($className === null || ! class_exists($className)) {
                continue;
            }

            if (! is_subclass_of($className, Model::class)) {
                continue;
            }

            if (substr($className, -4) === 'User') {
                continue;
            }

            $models[] = $className;
        }

        return array_values($models);
    }

    private function getClassNameFromFile(string $filePath): ?string
    {
        $contents = File::get($filePath);

        if ($contents === '') {
            return null;
        }

        $namespace = null;
        $className = null;

        $tokens = token_get_all($contents);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_NAMESPACE) {
                $namespace = $this->parseNamespace($tokens, $i + 1);
            }

            if (is_array($tokens[$i]) && $tokens[$i][0] === T_CLASS) {
                $className = $this->parseClassName($tokens, $i + 1);
                break;
            }
        }

        if ($className === null) {
            return null;
        }

        return $namespace ? $namespace.'\\'.$className : $className;
    }

    private function parseNamespace(array $tokens, int $startIndex): string
    {
        $namespace = '';

        for ($i = $startIndex, $count = count($tokens); $i < $count; $i++) {
            if (is_string($tokens[$i]) && $tokens[$i] === ';') {
                break;
            }

            if (is_array($tokens[$i])) {
                $namespace .= $tokens[$i][1];
            } else {
                $namespace .= $tokens[$i];
            }
        }

        return trim($namespace);
    }

    private function parseClassName(array $tokens, int $startIndex): ?string
    {
        for ($i = $startIndex, $count = count($tokens); $i < $count; $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                return $tokens[$i][1];
            }
        }

        return null;
    }

    public function addPrivacyFieldToModel(string $modelClass, string $field): bool
    {
        $reflection = new \ReflectionClass($modelClass);
        $filePath = $reflection->getFileName();

        if (! $filePath || ! File::exists($filePath)) {
            return false;
        }

        $contents = File::get($filePath);

        if ($contents === false) {
            return false;
        }

        if (str_contains($contents, "protected \$privacyFields =")) {
            $contents = $this->appendToExistingPrivacyFields($contents, $field);
        } else {
            $contents = $this->createPrivacyFieldsProperty($contents, $field);
        }

        $contents = $this->addHasPrivacyImport($contents);
        $contents = $this->addHasPrivacyTrait($contents);

        File::put($filePath, $contents);

        return true;
    }

    protected function appendToExistingPrivacyFields(string $contents, string $field): string
    {
        $pattern = '/protected\s+\$privacyFields\s*=\s*\[(.*?)\];/s';

        return preg_replace_callback($pattern, function (array $matches) use ($field): string {
            $body = $matches[1];

            preg_match_all("/['\"]([^'\"]+)['\"]/", $body, $existingFields);
            $fields = $existingFields[1] ?? [];

            if (! in_array($field, $fields, true)) {
                $fields[] = $field;
            }

            $fields = array_values(array_unique($fields));

            $formattedFields = array_map(
                static fn (string $item): string => "        '{$item}',",
                $fields
            );

            return "protected \$privacyFields = [\n".implode("\n", $formattedFields)."\n    ];";
        }, $contents) ?? $contents;
    }

    protected function createPrivacyFieldsProperty(string $contents, string $field): string
    {
        $pattern = '/class\s+\w+\s*(?:extends\s+[^{]+)?\{/';

        return preg_replace_callback($pattern, function (array $matches) use ($field): string {
            return $matches[0]."\n    protected \$privacyFields = [\n        '{$field}',\n    ];";
        }, $contents, 1) ?? $contents;
    }

    protected function addHasPrivacyImport(string $contents): string
    {
        $import = 'use BobKosse\DataSecurity\Traits\HasPrivacy;';

        if (str_contains($contents, $import)) {
            return $contents;
        }

        $pattern = '/(namespace\s+[^;]+;\s*)(.*?)(\r?\n\s*class\s+)/s';

        return preg_replace_callback($pattern, function (array $matches) use ($import): string {
            $beforeClass = $matches[2];

            if (preg_match('/use\s+[^;]+;/', $beforeClass)) {
                return $matches[1].trim($beforeClass)."\n".$import."\n\n".$matches[3];
            }

            return $matches[1].$import."\n\n".$matches[3];
        }, $contents, 1) ?? $contents;
    }

    protected function addHasPrivacyTrait(string $contents): string
    {
        if (str_contains($contents, 'use HasPrivacy;')) {
            return $contents;
        }

        $pattern = '/(class\s+\w+\s*(?:extends\s+[^{]+)?\{)/';

        return preg_replace_callback($pattern, function (array $matches): string {
            return $matches[1]."\n    use HasPrivacy;";
        }, $contents, 1) ?? $contents;
    }

    public function modelUsesHasPrivacy(string $modelClass): bool
    {
        if (! class_exists($modelClass)) {
            return false;
        }

        $reflection = new \ReflectionClass($modelClass);

        if (! $reflection->isSubclassOf(Model::class)) {
            return false;
        }

        return in_array(HasPrivacy::class, $reflection->getTraitNames(), true);
    }

    public function getPrivacyFields(string $modelClass): array
    {
        if (! class_exists($modelClass)) {
            return [];
        }

        $reflection = new \ReflectionClass($modelClass);

        if (! $reflection->isSubclassOf(Model::class)) {
            return [];
        }

        $instance = $reflection->newInstanceWithoutConstructor();

        if(method_exists($instance, 'getPrivacyFields')) {
            $fields = $instance->getPrivacyFields();
        } else {
            return [];
        }

        return is_array($fields) ? array_values($fields) : [];
    }

    public function getModelFields(string $modelClass): array
    {
        $model = new $modelClass();

        return Schema::getColumnListing($model->getTable());
    }

    public function fieldAlreadyExistsInPrivacyFields(string $modelClass, string $field): bool
    {
        $privacyFields = $this->getPrivacyFields($modelClass);

        return in_array($field, $privacyFields, true);
    }
}
