<?php
namespace DreamFactory\Core\Database\Schema;

use DreamFactory\Library\Utility\Inflector;

/**
 * TableSchema is the base class for representing the metadata of a database table.
 *
 * It may be extended by different DBMS driver to provide DBMS-specific table metadata.
 *
 * TableSchema provides the following information about a table:
 * <ul>
 * <li>{@link name}</li>
 * <li>{@link columns}</li>
 * <li>{@link primaryKey}</li>
 * <li>{@link foreignKeys}</li>
 * <li>{@link sequenceName}</li>
 * </ul>
 *
 * @property array $columnNames List of column names.
 */
class TableSchema extends NamedResourceSchema
{
    /**
     * @var string Optional plural form of the label for of this resource.
     */
    public $plural;
    /**
     * @var boolean Table or View?.
     */
    public $isView = false;
    /**
     * @var string Optional field of this table that may contain a displayable name for each row/record.
     */
    public $nameField;
    /**
     * @var string|array Primary key name of this table. If composite key, an array of key names is returned.
     */
    public $primaryKey;
    /**
     * @var string Sequence name for the primary key. Null if no sequence.
     */
    public $sequenceName;
    /**
     * @var array Column metadata of this table. Each array element is a ColumnSchema object, indexed by lowercase
     *      column name.
     */
    public $columns = [];
    /**
     * @var array Column aliases to column names.
     */
    public $columnAliases = [];
    /**
     * @var array Foreign keys of this table. The array is indexed by column name. Each value is an array of foreign
     *      table name and foreign column name.
     */
    public $foreignKeys = [];
    /**
     * @var array Relationship metadata of this table. Each array element is a RelationSchema object, indexed by
     *      lowercase relation name.
     */
    public $relations = [];
    /**
     * @var array Relationship aliases to relationship names.
     */
    public $relationAliases = [];
    /**
     * @var boolean Are any of the relationships required during fetch on this table?
     */
    public $fetchRequiresRelations = false;

    public function fill(array $settings)
    {
        foreach ($settings as $key => $value) {
            if ('field' === $key) {
                // reconstitute columns
                foreach ($value as $field) {
                    $temp = new ColumnSchema($field);
                    $this->addColumn($temp);
                }
                continue;
            }
            if ('related' === $key) {
                // reconstitute relations
                foreach ($value as $related) {
                    $temp = new RelationSchema($related);
                    $this->addRelation($temp);
                }
                continue;
            }
            if (!property_exists($this, $key)) {
                // try camel cased
                $camel = camel_case($key);
                if (property_exists($this, $camel)) {
                    $this->{$camel} = $value;
                    continue;
                }
            }
            // set real and virtual
            $this->{$key} = $value;
        }
    }

    public function getPlural($use_alias = false)
    {
        return (empty($this->plural)) ? Inflector::pluralize($this->getLabel($use_alias)) : $this->plural;
    }

    /**
     * Sets the named column metadata.
     *
     * @param ColumnSchema $schema
     */
    public function addColumn(ColumnSchema $schema)
    {
        $key = strtolower($schema->name);

        $this->columns[$key] = $schema;
    }

    /**
     * Gets the named column metadata.
     *
     * @param string $name column name
     * @param bool   $use_alias
     *
     * @return ColumnSchema metadata of the named column. Null if the named column does not exist.
     */
    public function getColumn($name, $use_alias = false)
    {
        if ($use_alias) {
            foreach ($this->columns as $column) {
                if (0 === strcasecmp($name, $column->getName($use_alias))) {
                    return $column;
                }
            }
        } else {
            $key = strtolower($name);
            if (isset($this->columns[$key])) {
                return $this->columns[$key];
            }
        }

        return null;
    }

    /**
     * @param bool $use_alias
     *
     * @return array list of column names
     */
    public function getColumnNames($use_alias = false)
    {
        $columns = [];
        /** @var ColumnSchema $column */
        foreach ($this->columns as $column) {
            $columns[] = $column->getName($use_alias);
        }

        return $columns;
    }

    /**
     * @param bool $use_alias
     *
     * @return ColumnSchema[]
     */
    public function getColumns($use_alias = false)
    {
        if ($use_alias) {
            // re-index for alias usage, easier to find requested fields from client
            $columns = [];
            /** @var ColumnSchema $column */
            foreach ($this->columns as $column) {
                $columns[strtolower($column->getName(true))] = $column;
            }

            return $columns;
        }

        return $this->columns;
    }

    public function addRelation(RelationSchema $relation)
    {
        if ($relation->alwaysFetch) {
            $this->fetchRequiresRelations = true;
        }
        $this->relations[strtolower($relation->name)] = $relation;
    }

    /**
     * Gets the named relation metadata.
     *
     * @param string $name relation name
     * @param bool   $use_alias
     *
     * @return RelationSchema metadata of the named relation. Null if the named relation does not exist.
     */
    public function getRelation($name, $use_alias = false)
    {
        if ($use_alias) {
            foreach ($this->relations as $relation) {
                if (0 === strcasecmp($name, $relation->getName($use_alias))) {
                    return $relation;
                }
            }
        } else {
            $key = strtolower($name);
            if (isset($this->relations[$key])) {
                return $this->relations[$key];
            }
        }

        return null;
    }

    /**
     * @param bool $use_alias
     *
     * @return array list of column names
     */
    public function getRelationNames($use_alias = false)
    {
        $relations = [];
        foreach ($this->relations as $relation) {
            $relations[] = $relation->getName($use_alias);
        }

        return $relations;
    }

    /**
     * @param bool $use_alias
     *
     * @return RelationSchema[]
     */
    public function getRelations($use_alias = false)
    {
        if ($use_alias) {
            // re-index for alias usage, easier to find requested fields from client
            $relations = [];
            /** @var RelationSchema $column */
            foreach ($this->relations as $column) {
                $relations[strtolower($column->getName(true))] = $column;
            }

            return $relations;
        }

        return $this->relations;
    }

    public function toArray($use_alias = false)
    {
        $out = parent::toArray($use_alias);

        $out['plural'] = $this->getPlural();
        $out['is_view'] = $this->isView;
        $out['primary_key'] = $this->primaryKey;
        $out['name_field'] = $this->nameField;

        $fields = [];
        /** @var ColumnSchema $column */
        foreach ($this->columns as $column) {
            $fields[] = $column->toArray($use_alias);
        }
        $out['field'] = $fields;

        $relations = [];
        /** @var RelationSchema $relation */
        foreach ($this->relations as $relation) {
            $relations[] = $relation->toArray($use_alias);
        }
        $out['related'] = $relations;

        return $out;
    }
}
