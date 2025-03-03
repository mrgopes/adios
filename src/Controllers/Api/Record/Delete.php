<?php

namespace ADIOS\Controllers\Api\Record;

class Delete extends \ADIOS\Core\ApiController {
  protected ?\Illuminate\Database\Eloquent\Builder $query = null;

  public \ADIOS\Core\Model $model;

  function __construct(\ADIOS\Core\Loader $app, array $params = [])
  {
    parent::__construct($app, $params);

    $model = $this->app->urlParamAsString('model');
    $this->permission = $model . ':Read';
    $this->model = $this->app->getModel($model);
  }

  public function response(): array
  {
    $ok = false;
    $rowsAffected = 0;

    if ($this->app->config->getAsBool('encryptRecordIds')) {
      $hash = $this->app->urlParamAsString('hash');
      $ok = $hash == \ADIOS\Core\Helper::encrypt($this->app->urlParamAsString('id'), '', true);
    } else {
      $id = $this->app->urlParamAsInteger('id');
      $ok = $id > 0;
    }

    if ($ok) {

      $error = '';
      $errorHtml = '';
      try {
        $rowsAffected = $this->model->record->delete($id);
      } catch (\Throwable $e) {
        $error = $e->getMessage();
        $errorHtml = $this->app->renderExceptionHtml($e);
      }

      $return = [
        'id' => $id,
        'status' => ($rowsAffected > 0),
      ];

      if ($error) $return['error'] = $error;
      if ($errorHtml) $return['errorHtml'] = $errorHtml;

      return $return;
    } else {
      return [
        'id' => $id,
        'status' => false,
      ];
    }
  }

}
