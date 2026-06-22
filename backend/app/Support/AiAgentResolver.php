<?php

namespace App\Support;

use App\Models\AiAgent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiAgentResolver
{
    public const MODULES = [
        'atendimento' => 'Atendimento',
        'rh' => 'RH',
        'financeiro' => 'Financeiro',
        'restaurante' => 'Restaurante',
        'pericia' => 'Perícia',
        'administrativo' => 'Administrativo',
    ];

    public const DEFAULT_MODULE = 'administrativo';

    public static function normalizeModule(?string $module): string
    {
        $key = strtolower(trim((string) $module));

        return array_key_exists($key, self::MODULES) ? $key : self::DEFAULT_MODULE;
    }

    public static function resolveForModule(?string $module = null): ?AiAgent
    {
        if (! Schema::hasTable('ai_agents')) {
            return null;
        }

        $moduleKey = self::normalizeModule($module);
        $agentId = null;

        if (Schema::hasTable('ai_agent_modules')) {
            $agentId = DB::table('ai_agent_modules')
                ->where('module_key', $moduleKey)
                ->value('agent_id');
        }

        if ($agentId) {
            $agent = AiAgent::query()->where('id', $agentId)->where('is_active', true)->first();
            if ($agent) {
                return $agent;
            }
        }

        return AiAgent::query()->where('is_active', true)->orderBy('id')->first();
    }

    /** @return array<string, string> module_key => agent name */
    public static function moduleBindings(): array
    {
        if (! Schema::hasTable('ai_agent_modules')) {
            return [];
        }

        $rows = DB::table('ai_agent_modules as m')
            ->leftJoin('ai_agents as a', 'm.agent_id', '=', 'a.id')
            ->select('m.module_key', 'm.agent_id', 'a.name as agent_name', 'a.is_active')
            ->get();

        $out = [];
        foreach (self::MODULES as $key => $label) {
            $row = $rows->firstWhere('module_key', $key);
            $out[$key] = [
                'module_key' => $key,
                'module_label' => $label,
                'agent_id' => $row->agent_id ?? null,
                'agent_name' => $row->agent_name ?? null,
                'agent_active' => (bool) ($row->is_active ?? false),
            ];
        }

        return $out;
    }

    public static function salvarBindings(array $bindings): void
    {
        if (! Schema::hasTable('ai_agent_modules')) {
            return;
        }

        foreach (self::MODULES as $key => $_label) {
            $agentId = $bindings[$key] ?? $bindings[$key.'_id'] ?? null;
            $agentId = ($agentId === '' || $agentId === null) ? null : (int) $agentId;

            $exists = DB::table('ai_agent_modules')->where('module_key', $key)->exists();
            if ($exists) {
                DB::table('ai_agent_modules')->where('module_key', $key)->update([
                    'agent_id' => $agentId,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('ai_agent_modules')->insert([
                    'module_key' => $key,
                    'agent_id' => $agentId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
