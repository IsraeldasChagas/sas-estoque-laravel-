<?php

namespace App\Models;

use App\Support\FiscalEmissaoConfigSupport;
use Illuminate\Database\Eloquent\Model;

class FiscalEmissaoConfig extends Model
{
    protected $table = 'fiscal_emissao_configs';

    protected $fillable = [
        'empresa_id',
        'provider',
        'environment',
        'api_url',
        'api_token',
        'certificado_pfx',
        'certificado_senha',
        'csc_id',
        'csc_token',
        'serie_nfce',
        'serie_nfe',
        'numero_proximo_nfce',
        'numero_proximo_nfe',
        'emitir_nfce_pdv',
        'modo_emissao_pdv',
        'emitir_nfe_pedido',
        'is_active',
        'status_emissao',
        'last_validated_at',
        'last_validation_message',
        'config_json',
        'observacoes',
    ];

    protected $hidden = [
        'api_token',
        'certificado_pfx',
        'certificado_senha',
        'csc_token',
    ];

    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
            'certificado_pfx' => 'encrypted',
            'certificado_senha' => 'encrypted',
            'csc_token' => 'encrypted',
            'config_json' => 'array',
            'emitir_nfce_pdv' => 'boolean',
            'emitir_nfe_pedido' => 'boolean',
            'is_active' => 'boolean',
            'last_validated_at' => 'datetime',
            'serie_nfce' => 'integer',
            'serie_nfe' => 'integer',
            'numero_proximo_nfce' => 'integer',
            'numero_proximo_nfe' => 'integer',
        ];
    }

    public static function mascararSegredo(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (strlen($value) <= 8) {
            return str_repeat('•', strlen($value));
        }

        return substr($value, 0, 4).'••••'.substr($value, -4);
    }

    /** @return array<string, mixed> */
    public function paraPainel(): array
    {
        return [
            'id' => $this->id,
            'empresa_id' => $this->empresa_id,
            'provider' => $this->provider,
            'environment' => $this->environment,
            'api_url' => $this->api_url,
            'api_token_mascarado' => self::mascararSegredo($this->api_token),
            'api_token_configurado' => $this->api_token !== null && $this->api_token !== '',
            'certificado_configurado' => $this->certificado_pfx !== null && $this->certificado_pfx !== '',
            'certificado_senha_configurada' => $this->certificado_senha !== null && $this->certificado_senha !== '',
            'csc_id' => $this->csc_id,
            'csc_token_mascarado' => self::mascararSegredo($this->csc_token),
            'csc_token_configurado' => $this->csc_token !== null && $this->csc_token !== '',
            'serie_nfce' => $this->serie_nfce,
            'serie_nfe' => $this->serie_nfe,
            'numero_proximo_nfce' => $this->numero_proximo_nfce,
            'numero_proximo_nfe' => $this->numero_proximo_nfe,
            'emitir_nfce_pdv' => (bool) $this->emitir_nfce_pdv,
            'modo_emissao_pdv' => $this->modo_emissao_pdv ?? 'opcional',
            'modo_emissao_pdv_label' => FiscalEmissaoConfigSupport::MODOS_EMISSAO_PDV[$this->modo_emissao_pdv ?? 'opcional'] ?? '',
            'emitir_nfe_pedido' => (bool) $this->emitir_nfe_pedido,
            'is_active' => (bool) $this->is_active,
            'status_emissao' => $this->status_emissao,
            'last_validated_at' => $this->last_validated_at?->toIso8601String(),
            'last_validation_message' => $this->last_validation_message,
            'config_json' => $this->config_json ?? [],
            'observacoes' => $this->observacoes,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> Dados para o futuro serviço de emissão (uso interno). */
    public function toEmitterArray(): array
    {
        return [
            'empresa_id' => $this->empresa_id,
            'provider' => $this->provider,
            'environment' => $this->environment,
            'api_url' => $this->api_url,
            'api_token' => $this->api_token,
            'certificado_pfx' => $this->certificado_pfx,
            'certificado_senha' => $this->certificado_senha,
            'csc_id' => $this->csc_id,
            'csc_token' => $this->csc_token,
            'serie_nfce' => $this->serie_nfce,
            'serie_nfe' => $this->serie_nfe,
            'numero_proximo_nfce' => $this->numero_proximo_nfce,
            'numero_proximo_nfe' => $this->numero_proximo_nfe,
            'emitir_nfce_pdv' => (bool) $this->emitir_nfce_pdv,
            'emitir_nfe_pedido' => (bool) $this->emitir_nfe_pedido,
            'config_json' => $this->config_json ?? [],
        ];
    }
}
