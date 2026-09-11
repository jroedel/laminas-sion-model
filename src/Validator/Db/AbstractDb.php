<?php

declare(strict_types=1);

namespace SionModel\Validator\Db;

use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\TableIdentifier;
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

    protected ?AdapterInterface $adapter = null;

    protected string $table = '';

    protected string $schema = '';

    protected string $field = '';

    /** @var array{field: string, value: mixed}|string|null */
    protected mixed $exclude = null;

    private ?Select $select = null;

    public function setAdapter(mixed $adapter): static
    {
        if (! $adapter instanceof AdapterInterface) {
            throw new RuntimeException('A database validator needs a laminas-db adapter');
        }

        $this->adapter = $adapter;

        return $this;
    }

    public function setTable(mixed $table): static
    {
        $this->table  = is_string($table) ? $table : '';
        $this->select = null;

        return $this;
    }

    public function setSchema(mixed $schema): static
    {
        $this->schema = is_string($schema) ? $schema : '';
        $this->select = null;

        return $this;
    }

    public function setField(mixed $field): static
    {
        $this->field  = is_string($field) ? $field : '';
        $this->select = null;

        return $this;
    }

    /** @param array{field: string, value: mixed}|string|null $exclude */
    public function setExclude(mixed $exclude): static
    {
        $this->exclude = $exclude;
        $this->select  = null;

        return $this;
    }

    /**
     * The query this validator runs, built once.
     *
     * Public because that is how it is recorded: `test/Rules/RuleSurface` renders it
     * through `Sql::prepareStatementForSqlObject()` and stores the statement.
     */
    public function getSelect(): Select
    {
        if (null !== $this->select) {
            return $this->select;
        }

        $select = new Select();
        $select->from(new TableIdentifier($this->table, '' === $this->schema ? null : $this->schema))
            ->columns([$this->field]);
        $select->where->equalTo($this->field, null);

        if (is_array($this->exclude)) {
            $select->where->notEqualTo($this->exclude['field'], $this->exclude['value']);
        } elseif (null !== $this->exclude) {
            $select->where($this->exclude);
        }

        return $this->select = $select;
    }

    /**
     * The first matching row, or null.
     *
     * `where1` is laminas-db's own name for the first bound parameter of the `WHERE`, and
     * binding by that name rather than rebuilding the select is what keeps one prepared
     * statement across a form's repeated submissions.
     */
    protected function queryFor(mixed $value): mixed
    {
        if (null === $this->adapter) {
            throw new RuntimeException('No database adapter present');
        }

        $sql        = new Sql($this->adapter);
        $statement  = $sql->prepareStatementForSqlObject($this->getSelect());
        $parameters = $statement->getParameterContainer();

        $parameters['where1'] = $value;

        return $statement->execute()->current();
    }
}
