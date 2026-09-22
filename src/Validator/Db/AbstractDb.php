<?php

declare(strict_types=1);

namespace SionModel\Validator\Db;

use SionModel\Db\Connection;
use SionModel\Db\Sql\Predicate\Operator;
use SionModel\Db\Sql\Select;
use SionModel\Validator\AbstractValidator;
use SionModel\Validator\Exception\RuntimeException;

use function is_array;
use function is_string;

/**
 * The half of a database validator that asks the question.
 *
 * Three specifications use one of the two subclasses: a phrase key must exist, an
 * assignment must exist, a library name must not. What they have in common is one query —
 * `SELECT <field> FROM <table> WHERE <field> = ?` — and the parameter is bound rather than
 * interpolated, which is the only reason a validator may take a value straight off a form
 * and put it in a `WHERE`.
 *
 * `test/Rules/rule-surface.php` records that statement for both subclasses, without a
 * database: a missing schema qualifier or a changed `WHERE` reads to a visitor as "record
 * not found" and refuses a perfectly good entry, which is the failure a verdict-based test
 * would not distinguish from the data simply not being there.
 *
 * laminas-db is still underneath. It leaves at iteration C, and this is one of the 94 files
 * that will have to change then.
 */
abstract class AbstractDb extends AbstractValidator
{
    public const ERROR_NO_RECORD_FOUND = 'noRecordFound';
    public const ERROR_RECORD_FOUND    = 'recordFound';

    /** @var array<string, string> */
    protected $messageTemplates = [
        self::ERROR_NO_RECORD_FOUND => 'No record matching the input was found',
        self::ERROR_RECORD_FOUND    => 'A record matching the input was found',
    ];

    protected ?Connection $adapter = null;

    protected string $table = '';

    protected string $schema = '';

    protected string $field = '';

    /** @var array{field: string, value: mixed}|string|null */
    protected mixed $exclude = null;

    public function setAdapter(mixed $adapter): static
    {
        if (! $adapter instanceof Connection) {
            throw new RuntimeException('A database validator needs a database connection');
        }

        $this->adapter = $adapter;

        return $this;
    }

    public function setTable(mixed $table): static
    {
        $this->table  = is_string($table) ? $table : '';

        return $this;
    }

    public function setSchema(mixed $schema): static
    {
        $this->schema = is_string($schema) ? $schema : '';

        return $this;
    }

    public function setField(mixed $field): static
    {
        $this->field  = is_string($field) ? $field : '';

        return $this;
    }

    /** @param array{field: string, value: mixed}|string|null $exclude */
    public function setExclude(mixed $exclude): static
    {
        $this->exclude = $exclude;

        return $this;
    }

    /**
     * The query this validator runs, for one value.
     *
     * Public because that is how it is recorded: `test/Rules/RuleSurface` renders it and
     * stores the statement. Called with no argument — which is how the recording calls it —
     * the comparison binds `null`, so what is recorded is the shape and not one interpolation
     * of it.
     *
     * It is built per call rather than once and rebound by parameter name. laminas-db's
     * `where1` trick kept a single prepared statement across a form's repeated submissions;
     * with emulated prepares there is no server-side statement to keep, so it bought a
     * positional assumption and nothing else.
     */
    public function getSelect(mixed $value = null): Select
    {
        //A schema qualifier is part of the name, and `Identifier::quote()` quotes a dotted
        //name segment by segment — which is what `TableIdentifier` existed to arrange.
        $table = '' === $this->schema ? $this->table : $this->schema . '.' . $this->table;

        $select = (new Select($table))->columns([$this->field]);
        $select->where(new Operator($this->field, Operator::EQ, $value));

        if (is_array($this->exclude)) {
            $select->where(new Operator($this->exclude['field'], Operator::NEQ, $this->exclude['value']));
        } elseif (null !== $this->exclude) {
            $select->where($this->exclude);
        }

        return $select;
    }

    /** The first matching row, or null. */
    protected function queryFor(mixed $value): mixed
    {
        if (null === $this->adapter) {
            throw new RuntimeException('No database connection present');
        }

        return $this->adapter->select($this->getSelect($value))->current();
    }
}
