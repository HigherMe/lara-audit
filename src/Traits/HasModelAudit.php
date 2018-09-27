<?php

namespace ItsMill3rTime\LaraAudit\Traits;

use Carbon\Carbon;
use ItsMill3rTime\LaraAudit\Models\ModelAudit;

trait HasModelAudit
{
    private $original_data = [];
    private $updated_data = [];
    private $fields_to_audit = [];
    protected $audit_fields = [];
    protected $dont_audit_fields = [];
    protected $dirty_data = [];
    private $updating = false;

    public static function bootHasModelAudit()
    {
        static::saving(function ($model) {
            $model->preSave();
        });
        static::saved(function ($model) {
            $model->postSave();
        });
        static::created(function ($model) {
            $model->postCreate();
        });
        static::restored(function ($model) {
            $model->postRestore();
        });
        static::deleting(function ($model) {
            $model->preDelete();
        });
        static::deleted(function ($model) {
            $model->preSave();
            $model->postDelete();
        });
    }

    public function preSave()
    {
        $this->loadIngores();
        $this->original_data = $this->original;
        $this->updated_data = $this->attributes;

        foreach ($this->updated_data as $key => $val) {
            if (gettype($val) == 'object' && !method_exists($val, '__toString')) {
                unset($this->original_data[$key]);
                unset($this->updated_data[$key]);
                array_push($this->dont_audit_fields, $key);
            }

            $this->fields_to_audit[] = $key;
        }

        if (count($this->audit_fields)) {
            $this->fields_to_audit = array_only($this->fields_to_audit, $this->audit_fields);
        }

        if (count($this->dont_audit_fields)) {
            $this->fields_to_audit = array_except($this->fields_to_audit, $this->dont_audit_fields);
        }

        $this->dirty_data = $this->getDirty();
        $this->updating = $this->exists;
    }

    public function postSave()
    {
        $this->loadIngores();
        if ($this->updating) {
            $revisions = [];
            foreach ($this->dirty_data as $column => $value) {
                if ($this->isSoftDelete() === false || ($this->isSoftDelete() && $column !== $this->getDeletedAtColumn())) {
                    $revisions[] = [
                        'model_type' => $this->getMorphClass(),
                        'model_id'   => $this->getKey(),
                        'column'     => $column,
                        'old_value'  => array_get($this->original_data, $column),
                        'new_value'  => array_get($this->updated_data, $column),
                        'user_id'    => $this->getAuthedUser(),
                        'operation'  => 'update',
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }
            }
            if (count($revisions)) {
                \DB::table('model_audits')->insert($revisions);
            }
        }
    }

    public function postCreate()
    {
        $this->loadIngores();
        $revisions[] = [
            'model_type' => $this->getMorphClass(),
            'model_id'   => $this->getKey(),
            'column'     => null,
            'old_value'  => null,
            'new_value'  => null,
            'user_id'    => $this->getAuthedUser(),
            'operation'  => 'create',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
        \DB::table('model_audits')->insert($revisions);
    }

    public function preDelete()
    {
        $this->loadIngores();
        if ($this->isSoftDelete() === false) {
            \DB::table('model_audits')->where('model_type', '=', $this->getMorphClass())->where('model_id', '=', $this->getKey())->delete();
        }
    }

    public function postDelete()
    {
        $this->loadIngores();
        if ($this->isSoftDelete()) {
            $revisions[] = [
                'model_type' => $this->getMorphClass(),
                'model_id'   => $this->getKey(),
                'column'     => null,
                'old_value'  => null,
                'new_value'  => null,
                'user_id'    => $this->getAuthedUser(),
                'operation'  => 'delete',
                'created_at' => new \DateTime(),
                'updated_at' => new \DateTime(),
            ];
            \DB::table('model_audits')->insert($revisions);
        }
    }

    public function postRestore()
    {
        $this->loadIngores();
        if ($this->isSoftDelete()) {
            $revisions[] = [
                'model_type' => $this->getMorphClass(),
                'model_id'   => $this->getKey(),
                'column'     => null,
                'old_value'  => null,
                'new_value'  => null,
                'user_id'    => $this->getAuthedUser(),
                'operation'  => 'restore',
                'created_at' => new \DateTime(),
                'updated_at' => new \DateTime(),
            ];
            \DB::table('model_audits')->insert($revisions);
        }
    }

    private function isSoftDelete()
    {
        if (isset($this->forceDeleting)) {
            return !$this->forceDeleting;
        }

        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses(get_class(self)))) {
            return true;
        }

        return false;
    }


    private function getAuthedUser()
    {
        if (\Auth::check()) {
            return \Auth::user()->getAuthIdentifier();
        } else {
            return null;
        }
    }

    public function auditTrail()
    {
        return $this->morphMany(ModelAudit::class, 'model');
    }

    public function getAuditTransforms()
    {
        if (is_array($this->audit_model_transforms)) {
            return $this->audit_model_transforms;
        }

        return [];
    }

    private function loadIngores()
    {
        if (isset($this->audit_model_ignore) && is_array($this->audit_model_ignore)) {
            $this->dont_audit_fields = array_merge($this->audit_model_ignore, $this->dont_audit_fields);
        }
    }
}