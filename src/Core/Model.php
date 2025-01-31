<?php

/*
  This file is part of ADIOS Framework.

  This file is published under the terms of the license described
  in the license.md file which is located in the root folder of
  ADIOS Framework package.
*/

namespace ADIOS\Core;

use ADIOS\Core\Db\DataType;
use ADIOS\Core\Db\Query;
use ADIOS\Core\Exceptions\DBException;
use ADIOS\Core\Exceptions\RecordSaveException;
use ADIOS\Core\ViewsWithController\Form;
use ADIOS\Core\ViewsWithController\Table;
use Closure;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use ReflectionClass;

/**
 * Core implementation of database model. Extends from Eloquent's model and adds own
 * functionalities.
 */
class Model
{
  const HAS_ONE = 'hasOne';
  const HAS_MANY = 'hasMany';
  const BELONGS_TO = 'belongsTo';

  /**
   * Full name of the model. Useful for getModel() function
   */
  public string $fullName = "";

  /**
   * Short name of the model. Useful for debugging purposes
   */
  public string $shortName = "";

  /**
   * Reference to ADIOS object
   *
   * @var mixed
   */
  public ?Loader $app = NULL;

  /**
   * Shorthand for "global table prefix"
   */
  public ?string $gtp = "";

  /**
   * Name of the table in SQL database. Used together with global table prefix.
   */
  public string $sqlName = '';

  /**
   * SQL-compatible string used to render displayed value of the record when used
   * as a lookup.
   */
  public ?string $lookupSqlValue = NULL;

  /**
   * If set to TRUE, the SQL table will not contain the ID autoincrement column
   */
  public bool $isJunctionTable = FALSE;

  public string $translationContext = '';

  var $pdo;

  /**
   * Property used to store original data when recordSave() method is calledmodel
   *
   * @var mixed
   */
  // var $recordSaveOriginalData = NULL;
  // protected string $fullTableSqlName = "";
  public string $table = '';
  public string $eloquentClass = '';
  public array $relations = [];

  public ?array $junctions = [];
  public \Illuminate\Database\Eloquent\Model $eloquent;


  /**
   * Creates instance of model's object.
   *
   * @param mixed $app
   * @return void
   */
  public function __construct(\ADIOS\Core\Loader $app)
  {
    $this->gtp = $app->configAsString('gtp');

    if (empty($this->table)) {
      $this->table = (empty($this->gtp) ? '' : $this->gtp . '_') . $this->sqlName; // toto je kvoli Eloquentu
    }

    $eloquentClass = $this->eloquentClass;
    if (empty($eloquentClass)) throw new Exception(get_class($this). ' - empty eloquentClass');
    $this->eloquent = new $eloquentClass;
    $this->eloquent->setTable($this->table);

    $this->fullName = str_replace("\\", "/", get_class($this));

    $tmp = explode("/", $this->fullName);
    $this->shortName = end($tmp);

    $this->app = $app;



    $reflection = new \ReflectionClass($this);
    $this->translationContext = strtolower(str_replace('\\', '.', $reflection->getName()));;

    try {
      $this->pdo = $this->eloquent->getConnection()->getPdo();
    } catch (\Throwable $e) {
      $this->pdo = null;
    }

    // During the installation no SQL tables exist. If child's init()
    // method uses data from DB, $this->init() call would fail.
    // Therefore the 'try ... catch'.
    try {
      $this->init();
    } catch (Exception $e) {
      //
    }

    if ($this->app->db) {
      $this->app->db->addTable(
        $this->table,
        $this->columnsLegacy(),
        $this->isJunctionTable
      );
    }

    $currentVersion = (int)$this->getCurrentInstalledVersion();
    $lastVersion = $this->getLastAvailableVersion();

    // if ($lastVersion == 0) {
    //   $this->saveConfig('installed-version', $lastVersion);
    // }

    if ($this->hasAvailableUpgrades()) {

      $this->app->userNotifications->addHtml("
        Model <b>{$this->fullName}</b> has new upgrades available (from {$currentVersion} to {$lastVersion}).
        <a
          href='javascript:void(0)'
          onclick='ADIOS.renderDesktop(\"Desktop/InstallUpgrades\");'
        >Install upgrades</a>
      ");
    } else if (!$this->hasSqlTable()) {
      $this->app->userNotifications->addHtml("
        Model <b>{$this->fullName}</b> has no SQL table.
        <a
          href='javascript:void(0)'
          onclick='ADIOS.renderDesktop(\"Desktop/InstallUpgrades\");'
        >Create table</a>
      ");
    } else if (!$this->isInstalled()) {
      $this->app->userNotifications->addHtml("
        Model <b>{$this->fullName}</b> is not installed.
        <a
          href='javascript:void(0)'
          onclick='ADIOS.renderDesktop(\"Desktop/InstallUpgrades\");'
        >Install model</a>
      ");
    }
  }

  /**
   * Empty placeholder for callback called after the instance has been created in constructor.
   *
   * @return void
   */
  public function init()
  { /* to be overriden */
  }

  /**
   * Retrieves value of configuration parameter.
   *
   * @return void
   */
  public function getConfig(string $configName): string
  {
    return $this->app->configAsString('models/' . str_replace("/", "-", $this->fullName) . '/' . $configName);
  }

  /**
   * Sets the value of configuration parameter.
   *
   * @return void
   */
  public function setConfig(string $configName, string $value): void
  {
    $this->app->setConfig('models/' . str_replace("/", "-", $this->fullName) . '/' . $configName, $value);
  }

  /**
   * Persistantly saves the value of configuration parameter to the database.
   *
   * @return void
   */
  public function saveConfig(string $configName, $value): void
  {
    $this->app->saveConfig([
      "models" => [
        str_replace("/", "-", $this->fullName) => [
          $configName => $value,
        ],
      ],
    ]);
  }

  /**
   * Shorthand for ADIOS core translate() function. Uses own language dictionary.
   *
   * @param string $string String to be translated
   * @param string $context Context where the string is used
   * @param string $toLanguage Output language
   * @return string Translated string.
   */
  public function translate(string $string, array $vars = []): string
  {
    return $this->app->translate($string, $vars, $this->translationContext);
  }

  public function hasSqlTable()
  {
    return in_array($this->table, $this->app->db->existingSqlTables ?? []);
  }

  /**
   * Checks whether model is installed.
   *
   * @return bool TRUE if model is installed, otherwise FALSE.
   */
  public function isInstalled(): bool
  {
    return $this->getConfig('installed-version') != "";
  }

  /**
   * Gets the current installed version of the model. Used during installing upgrades.
   *
   * @return void
   */
  public function getCurrentInstalledVersion(): int
  {
    return (int)($this->getConfig('installed-version') ?? 0);
  }

  public function getLastAvailableVersion(): int
  {
    return max(array_keys($this->upgrades()));
  }

  /**
   * Returns list of available upgrades. This method must be overriden by each model.
   *
   * @return array List of available upgrades. Keys of the array are simple numbers starting from 1.
   */
  public function upgrades(): array
  {
    return [
      0 => [], // upgrade to version 0 is the same as installation
    ];
  }

  /**
   * Installs the first version of the model into SQL database. Automatically creates indexes.
   *
   * @return void
   */
  public function install()
  {
    if (!empty($this->table)) {
      $this->app->db->createSqlTable($this->table);

      foreach ($this->indexes() as $indexOrConstraintName => $indexDef) {
        if (empty($indexOrConstraintName) || is_numeric($indexOrConstraintName)) {
          $indexOrConstraintName = md5(json_encode($indexDef) . uniqid());
        }

        $tmpColumns = "";

        foreach ($indexDef['columns'] as $tmpKey => $tmpValue) {
          if (!is_numeric($tmpKey)) {
            // v tomto pripade je nazov stlpca v kluci a vo value mozu byt dalsie nastavenia
            $tmpColumnName = $tmpKey;
            $tmpOrder = strtolower($tmpValue['order'] ?? 'asc');
            if (!in_array($tmpOrder, ['asc', 'desc'])) {
              $tmpOrder = 'asc';
            }
          } else {
            $tmpColumnName = $tmpValue;
            $tmpOrder = '';
          }

          $tmpColumns .=
            ($tmpColumns == '' ? '' : ', ')
            . '`' . $tmpColumnName . '`'
            . (empty($tmpOrder) ? '' : ' ' . $tmpOrder);
        }

        switch ($indexDef["type"]) {
          case "index":
            $this->app->db->query("
              alter table `" . $this->table . "`
              add index `{$indexOrConstraintName}` ({$tmpColumns})
            ");
            break;
          case "unique":
            $this->app->db->query("
              alter table `" . $this->table . "`
              add constraint `{$indexOrConstraintName}` unique ({$tmpColumns})
            ");
            break;
        }
      }

      $this->createSqlForeignKeys();

      $this->saveConfig('installed-version', max(array_keys($this->upgrades())));

      return TRUE;
    } else {
      return FALSE;
    }
  }

  public function hasAvailableUpgrades(): bool
  {
    $currentVersion = $this->getCurrentInstalledVersion();
    $lastVersion = $this->getLastAvailableVersion();
    return ($lastVersion > $currentVersion);
  }

  /**
   * Installs all upgrades of the model. Internaly stores current version and
   * compares it to list of available upgrades.
   *
   * @return void
   * @throws DBException When an error occured during the upgrade.
   */
  public function installUpgrades(): void
  {
    if ($this->hasAvailableUpgrades()) {
      $currentVersion = (int)$this->getCurrentInstalledVersion();
      $lastVersion = $this->getLastAvailableVersion();

      try {
        $this->app->db->startTransaction();

        $upgrades = $this->upgrades();

        for ($v = $currentVersion + 1; $v <= $lastVersion; $v++) {
          if (is_array($upgrades[$v])) {
            foreach ($upgrades[$v] as $query) {
              $this->app->db->query($query);
            }
          }
        }

        $this->app->db->commit();
        $this->saveConfig('installed-version', $lastVersion);
      } catch (DBException $e) {
        $this->app->db->rollback();
        throw new DBException($e->getMessage());
      }
    }
  }

  public function dropTableIfExists(): \ADIOS\Core\Model
  {
    $this->app->db->query("set foreign_key_checks = 0");
    $this->app->db->query("drop table if exists `" . $this->table . "`");
    $this->app->db->query("set foreign_key_checks = 1");
    return $this;
  }

  /**
   * Create foreign keys for the SQL table. Called when all models are installed.
   *
   * @return void
   */
  public function createSqlForeignKeys()
  {

    $sql = '';
    foreach ($this->columnsLegacy() as $column => $columnDefinition) {
      if (!empty($onlyColumn) && $onlyColumn != $column) continue;

      if (
        !($columnDefinition['disableForeignKey'] ?? false)
        && 'lookup' == $columnDefinition['type']
      ) {
        $lookupModel = $this->app->getModel($columnDefinition['model']);
        $foreignKeyColumn = $columnDefinition['foreignKeyColumn'] ?? "id";
        $foreignKeyOnDelete = $columnDefinition['foreignKeyOnDelete'] ?? "RESTRICT";
        $foreignKeyOnUpdate = $columnDefinition['foreignKeyOnUpdate'] ?? "RESTRICT";

        $sql .= "
          ALTER TABLE `{$this->table}`
          ADD CONSTRAINT `fk_" . md5($this->table . '_' . $column) . "`
          FOREIGN KEY (`{$column}`)
          REFERENCES `" . $lookupModel->getFullTableSqlName() . "` (`{$foreignKeyColumn}`)
          ON DELETE {$foreignKeyOnDelete}
          ON UPDATE {$foreignKeyOnUpdate};;
        ";
      }
    }

    if (!empty($sql)) {
      $this->app->db->multiQuery($sql);
    }

    // if (!empty($this->table)) {
    //   $this->app->db->createSqlForeignKeys($this->table);
    // }
  }

  /**
   * Returns full name of the model's SQL table
   *
   * @return string Full name of the model's SQL table
   */
  public function getFullTableSqlName()
  {
    return $this->table;
  }

  //////////////////////////////////////////////////////////////////
  // misc helper methods

  public function findForeignKeyModels()
  {
    $foreignKeyModels = [];

    foreach ($this->app->models as $model) {
      foreach ($model->columnsLegacy() as $colName => $colDef) {
        if (!empty($colDef["model"]) && $colDef["model"] == $this->fullName) {
          $foreignKeyModels[$model->fullName] = $colName;
        }
      }
    }

    return $foreignKeyModels;
  }

  public function getEnumValues()
  {
    $tmp = $this->eloquent
      ->selectRaw("{$this->table}.id")
      ->selectRaw("(" . str_replace("{%TABLE%}", $this->table, $this->lookupSqlValue()) . ") as ___lookupSqlValue")
      ->orderBy("___lookupSqlValue", "asc")
      ->get()
      ->toArray();

    $enumValues = [];
    foreach ($tmp as $key => $value) {
      $enumValues[$value['id']] = $value['___lookupSqlValue'];
    }

    return $enumValues;
  }

  //////////////////////////////////////////////////////////////////
  // definition of columns

  /** @return array<string, \ADIOS\Core\Db\Column> */
  public function columns(array $columns = []): array
  {
    $newColumns = [];

    if (!$this->isJunctionTable) {
      $newColumns['id'] = (new \ADIOS\Core\Db\Column\PrimaryKey($this, 'ID', 8));
    }

    foreach ($columns as $colName => $column) {
      $newColumns[$colName] = $column;
    }

    // default column settings
    // foreach ($columns as $colName => $colDefinition) {
    //   $newColumns[$colName] = $colDefinition;

    //   if ($colDefinition["type"] == "char") {
    //     $this->app->console->info("{$this->fullName}, {$colName}: char type is deprecated");
    //   }

    //   switch ($colDefinition["type"]) {
    //     case "int":
    //       $newColumns[$colName]["byte_size"] = $colDefinition["byte_size"] ?? 8;
    //       break;
    //     case "float":
    //       $newColumns[$colName]["byte_size"] = $colDefinition["byte_size"] ?? 14;
    //       $newColumns[$colName]["decimals"] = $colDefinition["decimals"] ?? 2;
    //       break;
    //     case "varchar":
    //     case "password":
    //       $newColumns[$colName]["byte_size"] = $colDefinition["byte_size"] ?? 255;
    //       break;
    //     case "lookup":
    //       $newColumns[$colName]["model"] = trim(str_replace("\\", "/", $newColumns[$colName]["model"]), "/");
    //       break;
    //   }
    // }

    // foreach ($newColumns as $colName => $colDef) {
    //   $colObject = $this->app->db->columnTypes[$colDef['type']] ?? null;

    //   if ($colObject instanceof DataType) {
    //     $newColumns[$colName] = $colObject->columnDefinitionPostProcess($colDef);
    //   }
    // }

    $this->eloquent->fillable = array_keys($newColumns);

    return $newColumns;
  }

  public function getColumn(string $column): Db\Column
  {
    return $this->columns()[$column];
  }

  /** @deprecated Use new definition of columns instead. */
  public function columnsLegacy(array $columns = []): array
  {
    $columns = $this->columns($columns);

    $columnsLegacy = [];
    foreach ($columns as $colName => $column) {
      if ($column instanceof \ADIOS\Core\Db\Column) {
        $columnsLegacy[$colName] = $column->toArray();
      } else if (is_array($column)) {
        $columnsLegacy[$colName] = $column;
      }
    }

    if (!$this->isJunctionTable) {
      $columnsLegacy['id'] = [
        'type' => 'int',
        'byte_size' => '8',
        'rawSqlDefinitions' => 'primary key auto_increment',
        'title' => 'ID',
        'readonly' => 'yes',
        'viewParams' => [
          'Table' => ['show' => TRUE],
          'Form' => ['show' => TRUE]
        ],
      ];
    }


    return $columnsLegacy;
  }

  /**
   * indexNames
   * @return array<string>
   */
  public function columnNames(): array
  {
    return array_keys($this->columns());
  }

  /**
   * indexes
   * @param array<string, mixed> $indexes
   * @return array<string, mixed>
   */
  public function indexes(array $indexes = []): array
  {
    return $indexes;
  }

  /**
   * indexNames
   * @return array<string>
   */
  public function indexNames(): array
  {
    return array_keys($this->indexNames());
  }

  //////////////////////////////////////////////////////////////////
  // CRUD methods

  public function getById(int $id)
  {
    $item = $this->recordGet(function($q) use ($id) { $q->where($this->table . '.id', $id); });
    return $item;
  }

  public function getLookupSqlValueById(int $id)
  {
    $row = $this->app->db->select($this)
      ->columnsLegacy([
        [$this->lookupSqlValue($this->table), 'lookup_value']
      ])
      ->where([['id', '=', $id]])
      ->fetch();

    return $row[0]['lookup_value'] ?? '';
  }

  public function insertRow($data)
  {
    return $this->app->db->insert($this)
      ->set($data)
      ->execute();
  }

  public function insertRowWithId($data)
  {
    return $this->app->db->insert($this)
      ->set($data)
      ->execute();
  }

  public function insertOrUpdateRow($data)
  {
    unset($data['id']);

    $duplicateKeyData = $data;

    return $this->app->db->insert($this)
      ->set($data)
      ->onDuplicateKey($duplicateKeyData)
      ->execute();
  }

  public function insertRandomRow($data = [], $dictionary = [])
  {
    return $this->insertRow(
      $this->app->db->getRandomColumnValues($this, $data, $dictionary)
    );
  }

  public function updateRow($data, $id)
  {
    $queryOk = $this->app->db->update($this)
      ->set($data)
      ->whereId((int)$id)
      ->execute();

    return ($queryOk ? $id : FALSE);
  }

  public function deleteRow($id)
  {
    return $this->app->db->delete($this)
      ->whereId((int)$id)
      ->execute();
  }

  public function copyRow($id)
  {
    $row = $this->app->db->select($this)
      ->columnsLegacy([Query::allColumnsWithoutLookups])
      ->where([
        ['id', '=', (int)$id]
      ])
      ->fetchOne();

    unset($row['id']);

    return $this->insertRow($row);
  }

  public function search($q)
  {
  }

  //////////////////////////////////////////////////////////////////
  // lookup processing methods

  // $initiatingModel = model formulara, v ramci ktoreho je lookup generovany
  // $initiatingColumn = nazov stlpca, z ktoreho je lookup generovany
  // $formData = aktualne data formulara
  public function lookupWhere(
    $initiatingModel = NULL,
    $initiatingColumn = NULL,
    $formData = [],
    $params = []
  )
  {
    return [];
  }

  // $initiatingModel = model formulara, v ramci ktoreho je lookup generovany
  // $initiatingColumn = nazov stlpca, z ktoreho je lookup generovany
  // $formData = aktualne data formulara
  public function lookupOrder(
    $initiatingModel = NULL,
    $initiatingColumn = NULL,
    $formData = [],
    $params = []
  )
  {
    return [['input_lookup_value', 'asc']];
  }

  // $initiatingModel = model formulara, v ramci ktoreho je lookup generovany
  // $initiatingColumn = nazov stlpca, z ktoreho je lookup generovany
  // $formData = aktualne data formulara
  public function lookupQuery(
    $initiatingModel = NULL,
    $initiatingColumn = NULL,
    $formData = [],
    $params = [],
    $having = "TRUE"
  ): Query
  {
    $where = $params['where'] ?? $this->lookupWhere($initiatingModel, $initiatingColumn, $formData, $params);
    $order = $params['order'] ?? $this->lookupOrder($initiatingModel, $initiatingColumn, $formData, $params);

    return $this->app->db->select($this)
      ->columnsLegacy([
        ['id', 'id'],
        [$this->lookupSqlValue($this->table), 'input_lookup_value']
      ])
      ->where($where)
      ->havingRaw($having)
      ->order($order);
  }

  // $initiatingModel = model formulara, v ramci ktoreho je lookup generovany
  // $initiatingColumn = nazov stlpca, z ktoreho je lookup generovany
  // $formData = aktualne data formulara
  public function lookupSqlQuery(
    $initiatingModel = NULL,
    $initiatingColumn = NULL,
    $formData = [],
    $params = [],
    $having = "TRUE"
  ): string
  {
    return $this->lookupQuery(
      $initiatingModel,
      $initiatingColumn,
      $formData,
      $params,
      $having
    )->buildSql();
  }

  public function lookupSqlValue($tableAlias = NULL): string
  {
    $value = $this->lookupSqlValue ?? "concat('{$this->fullName}, id = ', {%TABLE%}.id)";

    return ($tableAlias !== NULL
      ? str_replace('{%TABLE%}', "`{$tableAlias}`", $value)
      : $value
    );
  }

  public function columnDescribe(string $column): array
  {
    return (array) ($this->columns()[$column]->toArray() ?? []);
  }

  /**
  * @param array $description
  * @return array $description
  */
  public function tableDescribe(array $description = []): array
  {
    $columns = $this->columnsLegacy();
    unset($columns['id']);

    $description = [
      'ui' => [
        'showHeader' => true,
        'showFooter' => true,
        'showFilter' => true,
        'showHeaderTitle' => true,
      ],
      'columns' => $columns,
      'permissions' => [
        'canRead' => $this->app->permissions->granted($this->fullName . ':Read'),
        'canCreate' => $this->app->permissions->granted($this->fullName . ':Create'),
        'canUpdate' => $this->app->permissions->granted($this->fullName . ':Update'),
        'canDelete' => $this->app->permissions->granted($this->fullName . ':Delete'),
      ],
    ];

    return $description;
  }

  public function formDescribe(array $description = []): array {
    $columns = $this->columnsLegacy();
    unset($columns['id']);

    $description = [
      'ui' => [],
      'columns' => $columns,
      'defaultValues' => $this->recordDefaultValues(),
      'permissions' => [
        'canRead' => $this->app->permissions->granted($this->fullName . ':Read'),
        'canCreate' => $this->app->permissions->granted($this->fullName . ':Create'),
        'canUpdate' => $this->app->permissions->granted($this->fullName . ':Update'),
        'canDelete' => $this->app->permissions->granted($this->fullName . ':Delete'),
      ],
      'includeRelations' => [],
    ];

    return $description;
  }


  //////////////////////////////////////////////////////////////////
  // Column-related methods

  public function columnValidate(string $column, $value): bool
  {
    $valid = TRUE;

    $colDefinition = $this->columnsLegacy()[$column] ?? [];
    $colType = $colDefinition['type'];

    if ($this->app->db->isRegisteredColumnType($colType)) {
      $valid = $this->app->db->columnTypes[$colType]->validate($this, $value);
    }

    return $valid;
  }

  public function columnNormalize(string $column, $value)
  {
    $colDefinition = $this->columnsLegacy()[$column] ?? [];
    $colType = $colDefinition['type'];

    if ($this->app->db->isRegisteredColumnType($colType)) {
      $value = $this->app->db->columnTypes[$colType]->normalize($this, $column, $value, $colDefinition);
    }

    return $value;
  }

  public function columnGetNullValue(string $column)
  {
    $colDefinition = $this->columnsLegacy()[$column] ?? [];
    $colType = $colDefinition['type'];

    if ($this->app->db->isRegisteredColumnType($colType)) {
      $value = $this->app->db->columnTypes[$colType]->getNullValue($this, $column);
    }

    return $value;
  }

  //////////////////////////////////////////////////////////////////
  // Record-related methods

  // public function recordDescribe() {
  //   $description = [
  //     'columns' => $this->columnsLegacy(),
  //     'defaultValues' => $this->recordDefaultValues(),
  //   ];
  //   return $description;
  // }


  /**
   * recordValidate
   * @param array<string, mixed> $record
   * @return array<string, mixed>
   */
  public function recordValidate(array $record): array
  {
    $invalidInputs = [];

    foreach ($this->columnsLegacy() as $column => $colDefinition) {
      if (
        (bool) ($colDefinition['required'] ?? false)
        && (!isset($record[$column]) || $record[$column] === null || $record[$column] === '')
      ) {
        $invalidInputs[] = $this->app->translate(
          "`{{ colTitle }}` is required.",
          ['colTitle' => $colDefinition['title']]
        );
      } else if (
        isset($record[$column])
        && !$this->columnValidate($column, $record[$column])
      ) {
        $invalidInputs[] = $this->app->translate(
          "`{{ colTitle }}` contains invalid value.",
          ['colTitle' => $colDefinition['title']]
        );
      }
    }

    if (!empty($invalidInputs)) {
      throw new RecordSaveException(json_encode($invalidInputs), 87335);
    }

    return $record;
  }

  public function recordNormalize(array $record): array {
    $columns = $this->columnsLegacy();

    // Vyhodene, pretoze to v recordSave() sposobovalo mazanie udajov
    // foreach ($columns as $colName => $colDef) {
    //   if (!isset($record[$colName])) $record[$colName] = NULL;
    // }

    foreach ($record as $colName => $colValue) {
      if (!isset($columns[$colName])) {
        unset($record[$colName]);
      } else {
        $record[$colName] = $this->columnNormalize($colName, $record[$colName]);
        if ($record[$colName] === null) unset($record[$colName]);
      }
    }

    foreach ($columns as $colName => $colDef) {
      if (!isset($record[$colName])) $record[$colName] = $this->columnGetNullValue($colName);
    }

    return $record;
  }

  /**
   * Check if the lookup table needs the id of the inserted record from this model
   */
  private function ___getInsertedIdForLookupColumn(array $lookupColumns, array $lookupData, int $insertedRecordId): array
  {
    foreach ($lookupColumns as $lookupColumnName => $lookupColumnData) {
      if ($lookupColumnData['type'] != 'lookup') continue;

      if ($lookupColumnData['model'] == $this->fullName) {
        $lookupData[$lookupColumnName] = $insertedRecordId;
        break;
      }
    }

    return $lookupData;
  }

  private function ___validateBase64Image(string $base64String)
  {
    $pattern = '/^data:image\/[^;]+;base64,/';
    return preg_match($pattern, $base64String);
  }

  public function recordCreate(array $record): int {
    return $this->eloquent->create($record)->id;
  }

  public function recordUpdate(int $id, array $record): int {
    $this->eloquent->find($id)->update($record);
    return $id;
  }

  /** @return array<string, mixed> */
  public function recordSave(array $record): array
  {
    $id = (int) $record['id'];
    $isCreate = ($id <= 0);

    $originalRecord = $record;

    if ($isCreate) {
      $this->app->permissions->check($this->fullName . ':Create');
    } else {
      $this->app->permissions->check($this->fullName . ':Update');
    }

    $recordForThisModel = $record;

    $this->recordValidate($recordForThisModel);

    if ($isCreate) {
      $recordForThisModel = $this->onBeforeCreate($recordForThisModel);
    } else {
      $recordForThisModel = $this->onBeforeUpdate($recordForThisModel);
    }

    $recordForThisModel = $this->recordNormalize($recordForThisModel);

    $savedRecord = $record;

    if ($isCreate) {
      unset($recordForThisModel['id']);
      $savedRecord['id'] =  $this->recordCreate($recordForThisModel);
    } else {
      $savedRecord['id'] = $this->recordUpdate($id, $recordForThisModel);
    }

    // save cross-table-alignments
    foreach ($this->junctions as $jName => $jParams) {
      if (!isset($savedRecord[$jName])) continue;

      $junctions = $savedRecord[$jName] ?? NULL;
      if (!is_array($junctions)) {
        $junctions = @json_decode($savedRecord[$jName], TRUE);
      }

      if (is_array($junctions)) {
        $junctionModel = $this->app->getModel($jParams["junctionModel"]);

        $this->app->pdo->execute("
          delete from `{$junctionModel->getFullTableSqlName()}`
          where `{$jParams['masterKeyColumn']}` = ?
        ", [$savedRecord['id']]);

        foreach ($junctions as $junction) {
          $idOption = (int) $junction;
          if ($idOption > 0) {
            $this->app->pdo->execute("
              insert into `{$junctionModel->getFullTableSqlName()}` (
                `{$jParams['masterKeyColumn']}`,
                `{$jParams['optionKeyColumn']}`
              ) values (?, ?)
            ", [$savedRecord['id'], $idOption]);
          }
        }
      }
    }

    if ($isCreate) {
      $savedRecord = $this->onAfterCreate($originalRecord, $savedRecord);
    } else {
      $savedRecord = $this->onAfterUpdate($originalRecord, $savedRecord);
    }

    return $savedRecord;
  }

  public function recordDelete(int|string $id): bool
  {
    return $this->eloquent->where('id', $id)->delete();
  }

  /** @return array<string, mixed> */
  public function recordDefaultValues(): array
  {
    return [];
  }

  public function recordRelations(): array
  {
    $relations = [];

    foreach ($this->relations as $relName => $relDefinition) {
      $relations[$relName]['type'] = $relDefinition[0];
      $relations[$relName]['template'] = [$relDefinition[2] => ['_useMasterRecordId_' => true]];
    }

    return $relations;
  }

  public function loadRecords(callable|null $queryModifierCallback = null, array $includeRelations = [], int $maxRelationLevel = 0): array
  {
    $query = $this->prepareLoadRecordQuery($includeRelations, $maxRelationLevel);
    if ($queryModifierCallback !== null) $queryModifierCallback($query);

    $records = $query->get()?->toArray();

    if (!is_array($records)) $records = [];

    foreach ($records as $key => $record) {
      $records[$key] = $this->recordEncryptIds($records[$key]);
      // $records[$key] = $this->recordAddCustomData($records[$key]);
      $records[$key] = $this->onAfterLoadRecord($records[$key]);
      $records[$key]['_RELATIONS'] = array_keys($this->relations);
      if (count($includeRelations) > 0) $records[$key]['_RELATIONS'] = array_values(array_intersect($records[$key]['_RELATIONS'], $includeRelations));
    }

    $records = $this->onAfterLoadRecords($records);

    return $records;
  }

  public function recordEncryptIds(array $record) {

    foreach ($this->columnsLegacy() as $colName => $colDefinition) {
      if ($colName == 'id' || $colDefinition['type'] == 'lookup') {
        if ($record[$colName] !== null) {
          $record[$colName] = \ADIOS\Core\Helper::encrypt($record[$colName]);
        }
      }
    }

    $record['_idHash_'] =  \ADIOS\Core\Helper::encrypt($record['id'] ?? '', '', true);

    // foreach ($this->rela
    return $record;
  }

  public function recordDecryptIds(array $record) {
    foreach ($this->columnsLegacy() as $colName => $colDefinition) {
      if ($colName == 'id' || $colDefinition['type'] == 'lookup') {
        if (isset($record[$colName]) && $record[$colName] !== null && is_string($record[$colName])) {
          $record[$colName] = \ADIOS\Core\Helper::decrypt($record[$colName]);
        }
      }
    }

    foreach ($this->relations as $relName => $relDefinition) {
      if (!isset($record[$relName]) || !is_array($record[$relName])) continue;

      list($relType, $relModelClass) = $relDefinition;
      $relModel = new $relModelClass($this->app);

      switch ($relType) {
        case \ADIOS\Core\Model::HAS_MANY:
          foreach ($record[$relName] as $subKey => $subRecord) {
            $record[$relName][$subKey] = $relModel->recordDecryptIds($record[$relName][$subKey]);
          }
        break;
        case \ADIOS\Core\Model::HAS_ONE:
          $record[$relName] = $relModel->recordDecryptIds($record[$relName]);
        break;
      }
    }

    return $record;
  }

  public function recordGet(
    callable|null $queryModifierCallback = null,
    array $includeRelations = [],
    int $maxRelationLevel = 0
  ): array {
    $allRecords = $this->loadRecords($queryModifierCallback, $includeRelations, $maxRelationLevel);
    $record = reset($allRecords);
    if (!is_array($record)) $record = [];
    return $record;
  }

  public function recordGetWithRelations(
    callable|null $queryModifierCallback = null,
    int $maxRelationLevel = 4
  ): array {
    $allRecords = $this->loadRecords($queryModifierCallback, null, $maxRelationLevel);
    $record = reset($allRecords);
    if (!is_array($record)) $record = [];
    return $record;
  }

  public function recordGetList(
    array $includeRelations = [],
    int $maxRelationLevel = 0,
    string $search = '',
    array $filterBy = [],
    array $where = [],
    array $orderBy = [],
    int $itemsPerPage = 15,
    int $page = 0,
  ): array
  {
    $query = $this->prepareLoadRecordsQuery(
      $includeRelations,
      $maxRelationLevel,
      $search,
      $filterBy,
      $where,
      $orderBy,
      $itemsPerPage,
      $page
    );

    // Laravel pagination
    $data = $query->paginate(
      $itemsPerPage,
      ['*'],
      'page',
      $page
    )->toArray();

    if (!is_array($data)) $data = [];
    if (!is_array($data['data'])) $data['data'] = [];

    foreach ($data['data'] as $key => $record) {
      $data['data'][$key] = $this->recordEncryptIds($record);
      $data['data'][$key] = $this->recordAddCustomData($record);
      $data['data'][$key] = $this->onAfterLoadRecord($record);
      $data['data'][$key]['_RELATIONS'] = array_keys($this->relations);
    }

    return $data;
  }


  /**
   * prepareLoadRecordQuery
   * @param array $includeRelations Leave empty for default behaviour. What relations to be included in loaded record. If null, default relations will be selected.
   * @param int $maxRelationLevel Leave empty for default behaviour. Level of recursion in loading relations of relations.
   * @param mixed $query Leave empty for default behaviour.
   * @param int $level Leave empty for default behaviour.
   * @return mixed Eloquent query used to load record.
   */
  public function prepareLoadRecordQuery(array $includeRelations = [], int $maxRelationLevel = 0, mixed $query = null, int $level = 0): mixed
  {
    $tmpColumns = $this->columnsLegacy();

    if ($maxRelationLevel > 4) $maxRelationLevel = 4;

    $selectRaw = [];
    $withs = [];
    $joins = [];

    foreach ($this->columnsLegacy() as $colName => $colDefinition) {
      if ((bool) ($colDefinition['hidden'] ?? false)) continue;
      $selectRaw[] = $this->table . '.' . $colName;

      if (isset($colDefinition['enumValues']) && is_array($colDefinition['enumValues'])) {
        $tmpSelect = "CASE";
        foreach ($colDefinition['enumValues'] as $eKey => $eVal) {
          $tmpSelect .= " WHEN `{$this->table}`.`{$colName}` = '{$eKey}' THEN '{$eVal}'";
        }
        $tmpSelect .= " ELSE '' END AS `_ENUM[{$colName}]`";

        $selectRaw[] = $tmpSelect;
      }
    }

    $selectRaw[] = $level . ' as _LEVEL';
    $selectRaw[] = '(' . str_replace('{%TABLE%}', $this->table, $this->lookupSqlValue()) . ') as _LOOKUP';

    // LOOKUPS and RELATIONSHIPS
    foreach ($tmpColumns as $columnName => $column) {
      if ($column['type'] == 'lookup') {
        $lookupModel = $this->app->getModel($column['model']);
        $lookupConnection = $lookupModel->eloquent->getConnectionName();
        $lookupDatabase = $lookupModel->eloquent->getConnection()->getDatabaseName();
        $lookupTableName = $lookupModel->getFullTableSqlName();
        $joinAlias = 'join_' . $columnName;

        $selectRaw[] = "(" .
          str_replace("{%TABLE%}", $joinAlias, $lookupModel->lookupSqlValue())
          . ") as `_LOOKUP[{$columnName}]`"
        ;

        $joins[] = [
          $lookupDatabase . '.' . $lookupTableName . ' as ' . $joinAlias,
          $joinAlias.'.id',
          '=',
          $this->table.'.'.$columnName
        ];
      }
    }

    // TODO: Toto je pravdepodobne potencialna SQL injection diera. Opravit.
    if ($query === null) $query = $this->eloquent;
    $query = $query->selectRaw(join(',', $selectRaw)); //->with($withs);
    foreach ($this->relations as $relName => $relDefinition) {
      if (count($includeRelations) > 0 && !in_array($relName, $includeRelations)) continue;

      $relModel = new $relDefinition[1]($this->app);

      if ($maxRelationLevel > 0) {
        $query->with([$relName => function($q) use($relModel, $maxRelationLevel) {
          return $relModel->prepareLoadRecordQuery([], $maxRelationLevel - 1, $q);
        }]);
      }

    }
    foreach ($joins as $join) {
      $query->leftJoin($join[0], $join[1], $join[2], $join[3]);
    }

    return $query;
  }

  // prepare load query for MULTIPLE records
  public function prepareLoadRecordsQuery(
    array $includeRelations = [],
    int $maxRelationLevel = 0,
    string $search = '',
    array $filterBy = [],
    array $where = [],
    array $orderBy = []
  ): \Illuminate\Database\Eloquent\Builder
  {

    $columns = $this->columnsLegacy();
    $relations = $this->relations;

    $query = $this->prepareLoadRecordQuery(
      $includeRelations,
      $maxRelationLevel
    );

    // FILTER BY
    if (count($filterBy) > 0) {
      // TODO
    }

    // WHERE
    foreach ($where as $whereItem) {
      $query->where($whereItem[0], $whereItem[1], $whereItem[2]);
    }

    // Search
    if (!empty($search)) {
      foreach ($columns as $columnName => $column) {
        if (isset($column['enumValues'])) {
          $query->orHaving('_ENUM[' . $columnName . ']', 'like', "%{$search}%");
        }

        if ($column['type'] == 'lookup') {
          $query->orHaving('_LOOKUP[' . $columnName . ']', 'like', "%{$search}%");
        } else {
          $query->orHaving($columnName, 'like', "%{$search}%");
        }
      }
    }

    // orderBy
    $orderBy = $this->app->urlParamAsArray('orderBy');
    if (isset($orderBy['field']) && isset($orderBy['direction'])) {
      $query->orderBy( $orderBy['field'], $orderBy['direction']);
    }

    return $query;
  }

  /** @deprecated */
  public function getNewRecordDataFromString(string $text): array
  {
    return [];
  }

  //////////////////////////////////////////////////////////////////
  // callbacks

  /**
   * onBeforeCreate
   * @param array<string, mixed> $record
   * @return array<string, mixed>
   */
  public function onBeforeCreate(array $record): array
  {
    return $record;
  }

  /**
   * onBeforeUpdate
   * @param array<string, mixed> $record
   * @return array<string, mixed>
   */
  public function onBeforeUpdate(array $record): array
  {
    return $record;
  }

  /**
   * onAfterCreate
   * @param array<string, mixed> $originalRecord
   * @param array<string, mixed> $savedRecord
   * @return array<string, mixed>
   */
  public function onAfterCreate(array $originalRecord, array $savedRecord): array
  {
    return $savedRecord;
  }

  /**
   * onAfterUpdate
   * @param array<string, mixed> $originalRecord
   * @param array<string, mixed> $savedRecord
   * @return array<string, mixed>
   */
  public function onAfterUpdate(array $originalRecord, array $savedRecord): array
  {
    return $savedRecord;
  }


  public function onBeforeDelete(int $id): int
  {
    return $id;
  }

  public function onAfterDelete(int $id): int
  {
    return $id;
  }

  /**
   * onAfterLoadRecord
   * @param array<string, mixed> $record
   * @return array<string, mixed>
   */
  public function onAfterLoadRecord(array $record): array
  {
    return $record;
  }

  public function recordAddCustomData(array $record): array
  {
    return $record;
  }

  public function onAfterLoadRecords(array $records): array
  {
    return $records;
  }

}
