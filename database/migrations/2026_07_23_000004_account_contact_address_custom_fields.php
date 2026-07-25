<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->json('custom_fields')->nullable()->after('notes');
        });

        Schema::table('account_contact_infos', function (Blueprint $table) {
            $table->string('name')->nullable()->after('type');
            $table->string('relationship')->nullable()->after('name');
        });

        $this->setContactValueNullable(true);

        Schema::table('account_addresses', function (Blueprint $table) {
            $table->text('remarks')->nullable()->after('is_primary');
        });

        DB::table('account_contact_infos')->where('type', 'phone')->update(['type' => 'mobile']);

        $socialTypes = ['facebook', 'linkedin', 'x', 'instagram', 'website'];

        $socialLinks = DB::table('account_social_links')->whereNull('deleted_at')->get();
        foreach ($socialLinks as $link) {
            $platform = strtolower((string) $link->platform);
            $type = in_array($platform, $socialTypes, true) ? $platform : 'other';

            DB::table('account_contact_infos')->insert([
                'account_id' => $link->account_id,
                'type' => $type,
                'name' => null,
                'relationship' => null,
                'value' => $link->url,
                'label' => $link->label,
                'is_primary' => false,
                'notes' => null,
                'created_at' => $link->created_at,
                'updated_at' => $link->updated_at,
            ]);

            DB::table('account_social_links')->where('id', $link->id)->update([
                'deleted_at' => now(),
            ]);
        }

        $secondaries = DB::table('account_secondary_contacts')->whereNull('deleted_at')->get();
        foreach ($secondaries as $row) {
            $value = $row->phone ?: $row->email;

            DB::table('account_contact_infos')->insert([
                'account_id' => $row->account_id,
                'type' => 'secondary',
                'name' => $row->name,
                'relationship' => $row->relationship,
                'value' => $value,
                'label' => null,
                'is_primary' => false,
                'notes' => $row->notes,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);

            DB::table('account_secondary_contacts')->where('id', $row->id)->update([
                'deleted_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('account_addresses', function (Blueprint $table) {
            $table->dropColumn('remarks');
        });

        Schema::table('account_contact_infos', function (Blueprint $table) {
            $table->dropColumn(['name', 'relationship']);
        });

        $this->setContactValueNullable(false);

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('custom_fields');
        });

        DB::table('account_contact_infos')->where('type', 'mobile')->update(['type' => 'phone']);
    }

    private function setContactValueNullable(bool $nullable): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $nullSql = $nullable ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE account_contact_infos MODIFY value VARCHAR(255) {$nullSql}");

            return;
        }

        Schema::table('account_contact_infos', function (Blueprint $table) use ($nullable): void {
            $column = $table->string('value');
            $nullable ? $column->nullable()->change() : $column->nullable(false)->change();
        });
    }
};
