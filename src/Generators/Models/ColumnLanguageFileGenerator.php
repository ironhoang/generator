<?php
namespace LaravelRocket\Generator\Generators\Models;

use LaravelRocket\Generator\Objects\Column;
use function ICanBoogie\pluralize;

class ColumnLanguageFileGenerator extends ModelBaseGenerator
{
    /**
     * @return string
     */
    protected function getPath(): string
    {
        $modelName = $this->getModelName();
        $viewName  = kebab_case(pluralize($modelName));

        return resource_path('lang/en/tables/'.$this->table->getName().'/columns.php');
    }

    /**
     * @return string
     */
    protected function getView(): string
    {
        return 'model.column_language';
    }

    /**
     * @return array
     */
    protected function getVariables(): array
    {
        $modelName              = $this->getModelName();
        $variables              = $this->getColumns();
        $variables['modelName'] = $modelName;
        $variables['tableName'] = $this->table->getName();

        return $variables;
    }

    /**
     * @return array
     */
    protected function getColumns(): array
    {
        $columnInfo = [
            'columns'  => [],
            'booleans' => [],
            'options'  => [],
        ];

        foreach ($this->table->getColumns() as $column) {
            $columnDefinition = $this->json->getColumnDefinition($this->table->getName(), $column->getName());

            $name = $column->getName();

            $viewName = array_get($columnDefinition, 'name', '');
            if (empty($viewName)) {
                $viewName = $name;
                $viewName = preg_replace('/_id$/', ' ', $viewName);
                $viewName = title_case(preg_replace('/_/', ' ', $viewName));
            }

            $viewName                     = trim($viewName);
            $columnInfo['columns'][$name] = [
                'name'     => $viewName,
                'options'  => [],
                'booleans' => [],
            ];

            $columnObject = new Column($column);

            $type    = $columnObject->getEditFieldType();
            $options = $columnObject->getEditFieldOptions();

            switch ($type) {
                case 'boolean':
                    if (empty($options) || count($options) === 0) {
                        if ($name === 'is_enabled') {
                            $columnInfo['columns'][$name]['booleans']['true']  = 'Enabled';
                            $columnInfo['columns'][$name]['booleans']['false'] = 'Disabled';
                        } else {
                            $columnInfo['columns'][$name]['booleans']['true']  = 'Yes';
                            $columnInfo['columns'][$name]['booleans']['false'] = 'No';
                        }
                    } else {
                        foreach ($options as $option) {
                            $columnInfo['columns'][$name]['booleans'][array_get($option, 'value', '')] = array_get($option, 'name', '');
                        }
                    }
                    break;
            }

            $definitionType = array_get($columnDefinition, 'type');
            if ($definitionType === 'type') {
                $options = array_get($columnDefinition, 'options', []);
                $result  = [];
                foreach ($options as $index => $option) {
                    $optionValue          = array_get($option, 'value', $index);
                    $optionNme            = array_get($option, 'name', $index);
                    $result[$optionValue] = $optionNme;
                }
                $columnInfo['columns'][$name]['options'] = $result;
            }
        }

        return $columnInfo;
    }
}
