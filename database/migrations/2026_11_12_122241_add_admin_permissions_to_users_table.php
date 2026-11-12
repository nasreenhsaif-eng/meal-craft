<?php

use App\Enums\UserRole;
use App\Support\AdminPortalPermission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('admin_permissions')->nullable()->after('role');
        });

        $allPermissions = json_encode(AdminPortalPermission::keys());

        $seeded = DB::table('users')->where('email', 'test@example.com')->first();

        if ($seeded !== null) {
            DB::table('users')->where('id', $seeded->id)->update([
                'role' => UserRole::CraftMasterGeneral->value,
                'admin_permissions' => null,
            ]);
        }

        DB::table('users')
            ->where('role', UserRole::Admin->value)
            ->update([
                'admin_permissions' => $allPermissions,
            ]);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', UserRole::CraftMasterGeneral->value)
            ->update(['role' => UserRole::Admin->value]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('admin_permissions');
        });
    }
};
