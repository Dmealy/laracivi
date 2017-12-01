<?php
namespace DMealy\Laracivi;

use Illuminate\Support\Facades\Schema;

class CiviMigrationGenerator
{
    protected $schema;

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
        $schemaXml = $this->getSchema();
        $this->schema['database'] = [
            'name' => trim((string) $schemaXml->name),
            'comment' => $this->value('comment', $schemaXml, '')
        ];
        $this->generateTablesAndIndices($schemaXml);

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


    protected function generateTablesAndIndices($tableSchema)
    {
        $this->schema['tables'] = [];
        foreach ($tableSchema->tables as $tableGroup) {
            foreach ($tableGroup as $table) {
                $name = $this->value('name', $table);
                $this->schema['tables'][$name] = [
                    'name' => $name,
                    'comment' => $this->value('comment', $table),
                ];
                $this->schema['tables'][$name]['fields'] = $this->getFields($table->field);
            }
        }
    }

    protected function getFields($columns)
    {
        $fields = array();
        foreach ($columns as $values) {
            $name = $this->value('name', $values);
            $name = $this->getField('name', $values);
            $type = $this->getField('type', $values);
            $length = $this->getField('length', $values);
            $default = $this->getField('default', $values);
            if (is_bool($default)) {
                $default = $default === true ? 1 : 0;
            }
            $nullable = $this->getField('nullable', $values);
            $comment = $this->getField('comment', $values);
            $unsigned = $this->getField('unsigned', $values);
            $autoincrement = $this->getField('autoincrement', $values);
            $precision = $this->getField('precision', $values);
            $scale = $this->getField('scale', $values);
            $index = '';
            $decorators = null;
            $args = null;
            if (isset($this->fieldTypeMap[$type])) {
                $type = $this->fieldTypeMap[$type];
            }

            // Different rules for different type groups
            if (in_array($type, ['tinyInteger', 'smallInteger', 'integer', 'bigInteger'])) {
                // Integer
                if ($type == 'integer' and $unsigned and $autoincrement) {
                    $type = 'increments';
                    $index = null;
                } else {
                    if ($unsigned) {
                        $decorators[] = 'unsigned';
                    }
                    if ($autoincrement) {
                        $args = 'true';
                        $index = null;
                    }
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
                if ($unsigned) {
                    $decorators[] = 'unsigned';
                }
            } else {
                // Probably not a number (string/char)
                if ($type === 'string') {
                    $type = 'char';
                }
                $args = $this->getLength($length);
            }

            if ($nullable) {
                $decorators[] = 'nullable';
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
        if ($key == 'name') {
            return $result;
        }
        if ($key == 'nullable' and empty($this->value('required', $value))) {
            return 'nullable';
        }
        if ($key == 'autoincrement' and !empty($result)) {
            return 'autoincrement';
        }
        if ($key == 'default' and !empty($result)) {
            return "{$result}";
        }
        if ($key == 'type' and !empty($result)) {
            if ($result == 'int') {
                return 'integer';
            }
            return "{$result}";
        }
        if ($key == 'unsigned' and !empty($this->value('type', $value))) {
            if ($this->value('type', $value) == 'int unsigned') {
                return 'unsigned';
            }
            return '';
        }
        if ($key == 'comment' and !empty($result)) {
            return "{$result}";
        }

        return null;
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
