<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_accounts')) {
            return;
        }

        Schema::table('accounting_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounting_accounts', 'ifrs_category')) {
                $table->string('ifrs_category', 80)->nullable()->after('normal_balance');
            }

            if (! Schema::hasColumn('accounting_accounts', 'statement_report')) {
                $table->string('statement_report', 80)->nullable()->after('ifrs_category');
            }

            if (! Schema::hasColumn('accounting_accounts', 'statement_section')) {
                $table->string('statement_section', 120)->nullable()->after('statement_report');
            }

            if (! Schema::hasColumn('accounting_accounts', 'statement_subsection')) {
                $table->string('statement_subsection', 120)->nullable()->after('statement_section');
            }

            if (! Schema::hasColumn('accounting_accounts', 'statement_order')) {
                $table->unsignedSmallInteger('statement_order')->default(999)->after('statement_subsection');
            }

            if (! Schema::hasColumn('accounting_accounts', 'cash_flow_category')) {
                $table->string('cash_flow_category', 80)->nullable()->after('statement_order');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_accounts')) {
            return;
        }

        Schema::table('accounting_accounts', function (Blueprint $table) {
            foreach ([
                'cash_flow_category',
                'statement_order',
                'statement_subsection',
                'statement_section',
                'statement_report',
                'ifrs_category',
            ] as $column) {
                if (Schema::hasColumn('accounting_accounts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
