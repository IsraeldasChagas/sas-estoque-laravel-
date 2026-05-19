<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('funcionarios')) {
      return;
    }

    if (! Schema::hasColumn('funcionarios', 'cpf_limpo')) {
      Schema::table('funcionarios', function (Blueprint $table) {
        $table->char('cpf_limpo', 11)->nullable()->after('cpf');
        $table->index('cpf_limpo');
      });
    }

    $rows = DB::table('funcionarios')->select('id', 'cpf')->get();
    foreach ($rows as $row) {
      $limpo = preg_replace('/\D/', '', (string) ($row->cpf ?? ''));
      if (strlen($limpo) === 11) {
        DB::table('funcionarios')->where('id', $row->id)->update(['cpf_limpo' => $limpo]);
      }
    }
  }

  public function down(): void
  {
    if (! Schema::hasTable('funcionarios') || ! Schema::hasColumn('funcionarios', 'cpf_limpo')) {
      return;
    }

    Schema::table('funcionarios', function (Blueprint $table) {
      $table->dropIndex(['cpf_limpo']);
      $table->dropColumn('cpf_limpo');
    });
  }
};
