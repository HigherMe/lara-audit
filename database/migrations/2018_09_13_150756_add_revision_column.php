<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRevisionColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('model_audits', function (Blueprint $table) {
            $table->string('revision')->after('operation');
        });

        \DB::table('model_audits')->select(\DB::raw('distinct model_type,model_id,user_id,DATE_FORMAT(created_at,\'%Y-%m-%d\') as created_day'))
            ->orderBy('created_day', 'asc')->chunk(10, function ($model_audit_records) {
                foreach ($model_audit_records as $model_audit_record) {
                    $revision = uniqid() . $model_audit_record->model_id;
                    \DB::table('model_audits')
                        ->where('model_type', '=', $model_audit_record->model_type)
                        ->where('model_id', '=', $model_audit_record->model_id)
                        ->whereRaw('DATE_FORMAT(created_at,\'%Y-%m-%d\') = "' . $model_audit_record->created_day . '"')
                        ->update([
                            'revision' => $revision
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('model_audits', function (Blueprint $table) {
            $table->dropColumn('revision');
        });
    }
}
