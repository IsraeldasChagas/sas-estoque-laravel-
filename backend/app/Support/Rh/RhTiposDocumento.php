<?php

namespace App\Support\Rh;

final class RhTiposDocumento
{
    /** Documentos enviados como PDF (ou imagem digitalizada em PDF). */
    public const PDF_ONLY = [
        'ctps',
        'exame_admissional',
        'rg',
        'cpf',
        'titulo_eleitor',
        'reservista',
        'cnh_motorista',
        'certidao_casamento',
        'documentos_filhos_menores',
        'comprovante',
        'pis',
    ];

    /** Foto recente estilo 3×4 — JPG ou PNG. */
    public const IMAGE_ONLY = [
        'foto_3x4',
    ];

    /** @return array<string, string> código => rótulo para candidatos / RH */
    public static function rotulos(): array
    {
        return [
            'ctps' => 'CTPS (carteira de trabalho digital ou PDF)',
            'exame_admissional' => 'Exame admissional',
            'rg' => 'RG (digital ou PDF)',
            'cpf' => 'CPF (digital ou PDF)',
            'titulo_eleitor' => 'Título de eleitor (digital ou PDF)',
            'reservista' => 'Carteira de reservista (digital ou PDF)',
            'cnh_motorista' => 'CNH — função motorista (digital ou PDF)',
            'certidao_casamento' => 'Certidão de casamento (se for o caso, PDF)',
            'documentos_filhos_menores' => 'Filhos menores de 14 anos (certidão de nascimento, carteira de vacinação, declaração escolar e CPF — um PDF)',
            'comprovante' => 'Comprovante de residência (digital ou PDF)',
            'foto_3x4' => 'Foto 3×4 (arquivo digital JPG ou PNG)',
            'pis' => 'Cartão do PIS / Meu INSS (digital ou PDF)',
        ];
    }

    /** @return list<string> */
    public static function todosTipos(): array
    {
        return array_merge(self::PDF_ONLY, self::IMAGE_ONLY);
    }

    /** @return list<string> */
    public static function mimePermitidosParaTipo(string $tipo): array
    {
        if (in_array($tipo, self::IMAGE_ONLY, true)) {
            return ['image/jpeg', 'image/png'];
        }

        return ['application/pdf'];
    }
}
