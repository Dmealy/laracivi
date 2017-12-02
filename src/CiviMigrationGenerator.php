<?php
namespace DMealy\Laracivi;

use Illuminate\Support\Facades\Schema;

class CiviMigrationGenerator
{
    protected $schema;
    protected $schemaXml;

    /**
     * Convert civi types to Laravel Migration Types
     * @var array
     */
    protected $fieldTypeMap = [
        'tinyint'  => 'tinyInteger',
        'smallint' => 'smallInteger',
        'bigint'   => 'bigInteger',
        'datetime' => 'dateTime',
        'blob'     => 'binary',
    ];


    public function generate()
    {
        $this->schemaXml = $this->getSchema();
        $this->schema['database'] = [
            'name' => trim((string) $this->schemaXml->name),
            'comment' => $this->value('comment', $this->schemaXml, '')
        ];
        $this->generateMigrationSchema();

        return 'NOT YET DONE  Civicrm migration created.';
    }


    /**
     * Converts Schema.xml to a SimpleXMLElement object.
     *
     * @return SimpleXMLElement
     */
    protected function getSchema()
    {
        $file = base_path('vendor/dmealy/civicrm-core/xml/schema/Schema.xml');
        $dom = new \DomDocument();
        $xmlString = file_get_contents($file);
        $dom->loadXML($xmlString);
        $dom->documentURI = $file;
        $dom->xinclude();

        return simplexml_import_dom($dom);
    }


    protected function generateMigrationSchema()
    {
        $this->schema['tables'] = [];
        foreach ($this->schemaXml->tables as $tableGroup) {
            foreach ($tableGroup as $table) {
                $name = $this->value('name', $table);
                $this->schema['tables'][$name] = [
                    'name' => $name,
                    'comment' => $this->value('comment', $table),
                ];
                $results = $this->getFields($table);
                $results = array_merge($results, $this->getIndices($table));
                $this->schema['tables'][$name]['fields'] = $results;
                $this->schema['tables'][$name]['foreignkeys'] = $this->getForiegnKeys($table);
            }
        }
    }

    protected function getFields($tableXml)
    {
        $fields = array();
        foreach ($tableXml->field as $values) {
            $name = $this->getField('name', $values);
            $type = $this->getField('type', $values);
            $length = $this->getField('length', $values);
            $default = $this->getField('default', $values);
            if (is_bool($default)) {
                $default = $default === true ? 1 : 0;
            }
            $nullable = $this->getField('nullable', $values);
            $comment = $this->getField('comment', $values);
            $unsigned = empty($unsigned) ? $this->getField('unsigned', $values) : $unsigned;
            $precision = $this->getField('precision', $values);
            $scale = $this->getField('scale', $values);
            $index = '';
            $decorators = null;
            $args = null;

            if (in_array($type, ['tinyInteger', 'smallInteger', 'integer', 'bigInteger', 'int unsigned'])) {
                if ($tableXml->primaryKey->name == $name and $tableXml->primaryKey->autoincrement == 'true') {
                    $type = 'increments';
                } elseif ($type == 'int unsigned') {
                    $type = 'integer';
                    $unsigned = 'unsigned';
                }
            } elseif ($type == 'dateTime') {
                if ($name == 'deleted_at' and $nullable) {
                    $nullable = false;
                    $type = 'softDeletes';
                    $name = '';
                } elseif ($name == 'created_at' and isset($fields['updated_at'])) {
                    $fields['updated_at'] = ['field' => '', 'type' => 'timestamps'];
                    continue;
                } elseif ($name == 'updated_at' and isset($fields['created_at'])) {
                    $fields['created_at'] = ['field' => '', 'type' => 'timestamps'];
                    continue;
                }
            } elseif (in_array($type, ['decimal', 'float', 'double'])) {
                // Precision based numbers
                $args = $this->getPrecision($precision, $scale);
            } else {
                if ($type === 'varchar') {
                    $type = 'string';
                }
                $args = $this->getLength($length);
            }

            if ($nullable) {
                $decorators[] = 'nullable';
            }
            if ($unsigned) {
                $decorators[] = 'unsigned';
            }
            if ($default !== null) {
                $decorators[] = $this->getDefault($default, $type);
            }
            if ($index) {
                $decorators[] = $this->decorate($index->type, $index->name);
            }
            if ($comment) {
                $decorators[] = "comment('" . addcslashes($comment, "\\'") . "')";
            }
            $field = ['field' => $name, 'type' => $type];
            if ($decorators) {
                $field['decorators'] = $decorators;
            }
            if ($args) {
                $field['args'] = $args;
            }
            $fields[$name] = $field;
        }

        return $fields;
    }

    protected function getIndices($tableXml)
    {
        $indices = array();
        foreach ($tableXml->index as $values) {
            $name = $this->getField('name', $values);
            $type = $this->getField('unique', $values) ? 'unique' : 'index';
            $fields = [];
            foreach ($values->fieldName as $key => $fieldName) {
                $fields[] = (string) $fieldName;
            }
            if (!$name) {
                $name = 'index_' . implode('_', $fields);
            }
            $indices[$name] = [
                'field' => $fields,
                'type' => $type,
                'args' => $name,
            ];
        }

        return $indices;
    }

    protected function getForiegnKeys($tableXml)
    {
        $constraints = array();
        foreach ($tableXml->foreignKey as $values) {
            $field = $this->getField('name', $values);
            $type = 'foreign';
            $table = $this->getField('table', $values);
            $key = $this->getField('key', $values);
            $onDelete = $this->getField('onDelete', $values);
            $onUpdate = ($this->getField('onUpdate', $values) ?: 'restrict');
            $name = 'fk_' . camel_case($table) . '_' . $field;
            $constraints[$name] = [
                'name' => $name,
                'field' => $field,
                'references' => $key,
                'on' => $table,
                'onUpdate' => $onUpdate,
                'onDelete' => $onDelete,
            ];
        }

        return $constraints;
    }

    /**
     * Generates appropriate value for a given field attribute.
     *
     * @param  string $key
     * @param  SimpleXMLElement $value
     * @return string
     */
    protected function getField($key, $value)
    {
        $result = $this->value($key, $value);
        if ($key == 'nullable' and empty($this->value('required', $value))) {
            return 'nullable';
        }
        if ($key == 'unsigned' and !empty($this->value('type', $value))) {
            if ($this->value('type', $value) == 'int unsigned') {
                return 'unsigned';
            }
        }

        return $result;
    }

    /**
     * @param int $precision
     * @param int $scale
     * @return string|void
     */
    protected function getPrecision($precision, $scale)
    {
        if ($precision != 8 or $scale != 2) {
            $result = $precision;
            if ($scale != 2) {
                $result .= ', ' . $scale;
            }
            return $result;
        }
    }
    /**
     * @param int $length
     * @return int|void
     */
    protected function getLength($length)
    {
        if ($length and $length !== 255) {
            return $length;
        }
    }

    /**
     * @param string $default
     * @param string $type
     * @return string
     */
    protected function getDefault($default, &$type)
    {
        if (in_array($default, ['CURRENT_TIMESTAMP'], true)) {
            if ($type == 'dateTime') {
                $type = 'timestamp';
            }
            $default = $this->decorate('DB::raw', $default);
        } elseif (in_array($type, ['string', 'text']) or !is_numeric($default)) {
            $default = $this->argsToString($default);
        }
        return $this->decorate('default', $default, '');
    }

    /**
     * @param string|array $args
     * @param string       $quotes
     * @return string
     */
    protected function argsToString($args, $quotes = '\'')
    {
        if (is_array($args)) {
            $separator = $quotes .', '. $quotes;
            $args = implode($separator, str_replace($quotes, '\\'.$quotes, $args));
        } else {
            $args = str_replace($quotes, '\\'.$quotes, $args);
        }

        return $quotes . $args . $quotes;
    }

    /**
     * Get Decorator
     * @param string       $function
     * @param string|array $args
     * @param string       $quotes
     * @return string
     */
    protected function decorate($function, $args, $quotes = '\'')
    {
        if (! is_null($args)) {
            $args = $this->argsToString($args, $quotes);
            return $function . '(' . $args . ')';
        } else {
            return $function;
        }
    }

    /**
    * @param $key
    * @param $object
    * @param null $default
    *
    * @return null|string
    */
    protected function value($key, &$object, $default = null)
    {
        if (isset($object->$key)) {
            return (string ) $object->$key;
        }
        return $default;
    }
}
