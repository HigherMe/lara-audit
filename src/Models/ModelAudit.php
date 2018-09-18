<?php

namespace Itsmill3rtime\LaraAudit\Models;

use Illuminate\Database\Eloquent\Model;

class ModelAudit extends Model
{
    protected $guarded = [];
    protected $appends = ['display_column', 'old_display_value', 'new_display_value'];
    protected $with = ['actor', 'model'];

    public function model()
    {
        return $this->morphTo();
    }

    public function getDisplayColumnAttribute()
    {
        if (in_array($this->operation, ['delete', 'restore'])) {
            $class_name = explode("\\", $this->model_type);

            return end($class_name);
        }

        $audit_transform = array_get(with(new $this->model_type)->getAuditTransforms(), $this->column);
        if ($this->checkTransformKeys($audit_transform) === false) {
            return $this->column;
        }

        return array_get($audit_transform, 'display', $this->column);
    }

    public function getNewDisplayValueAttribute()
    {
        if (in_array($this->operation, ['delete', 'restore'])) {
            return $this->operation . 'd';
        }

        $audit_transform = array_get(with(new $this->model_type)->getAuditTransforms(), $this->column, []);

        if ($this->checkTransformKeys($audit_transform) === false) {
            return $this->new_value;
        }

        $remote_model_type = data_get($audit_transform, 'model');
        $remote_model_column = data_get($audit_transform, 'column');

        $builder = (new $remote_model_type)->where($remote_model_column, '=', $this->old_value);
        if ($this->doesModelHaveSoftDeletes($remote_model_type)) {
            $remote_model = $builder->withTrashed()->first();
        } else {
            $remote_model = $builder->first();
        }

        if (empty($remote_model)) {
            return $this->new_value;
        }

        return (string)$remote_model;
    }

    public function getOldDisplayValueAttribute()
    {
        if (in_array($this->operation, ['delete', 'restore'])) {
            return '';
        }

        $audit_transform = array_get(with(new $this->model_type)->getAuditTransforms(), $this->column, []);

        if ($this->checkTransformKeys($audit_transform) === false) {
            return $this->old_value;
        }

        $remote_model_type = data_get($audit_transform, 'model');
        $remote_model_column = data_get($audit_transform, 'column');
        $builder = (new $remote_model_type)->where($remote_model_column, '=', $this->old_value);
        if ($this->doesModelHaveSoftDeletes($remote_model_type)) {
            $remote_model = $builder->withTrashed()->first();
        } else {
            $remote_model = $builder->first();
        }
        if (empty($remote_model)) {
            return $this->old_value;
        }

        return (string)$remote_model;
    }

    private function checkTransformKeys($audit_transform_array)
    {
        if (!is_array($audit_transform_array)) {
            return false;
        }

        $keys = array_keys($audit_transform_array);
        if (!in_array('model', $keys) || !in_array('column', $keys) || !in_array('display', $keys)) {
            return false;
        }

        return true;
    }

    private function doesModelHaveSoftDeletes($model_class)
    {
        $test_model = new $model_class;

        if (isset($test_model->forceDeleting)) {
            return !$test_model->forceDeleting;
        }


        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($test_model))) {
            return true;
        }

        return false;
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
