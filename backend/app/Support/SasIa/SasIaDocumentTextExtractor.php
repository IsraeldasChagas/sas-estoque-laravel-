<?php

namespace App\Support\SasIa;

use Illuminate\Http\UploadedFile;

/**
 * Extrai texto de arquivos enviados para a base de conhecimento do SAS IA.
 */
class SasIaDocumentTextExtractor
{
    private const MAX_CHARS = 50000;

    /** @var array<int, string> */
    private const EXTENSOES = ['txt', 'md', 'csv', 'json', 'log', 'pdf', 'docx'];

    public static function extensoesPermitidas(): array
    {
        return self::EXTENSOES;
    }

    public static function fromUploadedFile(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: '');
        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            throw new \RuntimeException('Não foi possível ler o arquivo enviado.');
        }

        return self::fromPath($path, $ext);
    }

    public static function fromPath(string $path, string $ext): string
    {
        $ext = strtolower(trim($ext) ?: pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($ext, self::EXTENSOES, true)) {
            throw new \InvalidArgumentException(
                'Formato não suportado. Use: '.implode(', ', self::EXTENSOES).'.'
            );
        }

        if (! is_readable($path)) {
            throw new \RuntimeException('Não foi possível ler o arquivo.');
        }

        $texto = match ($ext) {
            'pdf' => self::fromPdf($path),
            'docx' => self::fromDocx($path),
            default => self::fromPlainTextFile($path),
        };

        $texto = self::normalize($texto);
        if ($texto === '') {
            throw new \InvalidArgumentException(
                'Não foi possível extrair texto do arquivo. Tente PDF com texto selecionável ou cole o conteúdo manualmente.'
            );
        }

        return mb_substr($texto, 0, self::MAX_CHARS);
    }

    private static function fromPlainTextFile(string $path): string
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            return '';
        }

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
        }

        return $raw;
    }

    private static function fromPdf(string $path): string
    {
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            try {
                $parser = new \Smalot\PdfParser\Parser;
                $pdf = $parser->parseFile($path);

                return (string) $pdf->getText();
            } catch (\Throwable) {
                // tenta fallback abaixo
            }
        }

        return self::fromPdfBasico($path);
    }

    private static function fromPdfBasico(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return '';
        }

        $parts = [];
        if (preg_match_all('/\((?:\\\\.|[^\\\\])*?\)/s', $content, $matches)) {
            foreach ($matches[0] as $chunk) {
                $inner = substr($chunk, 1, -1);
                $inner = str_replace(
                    ['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'],
                    ['(', ')', '\\', "\n", "\r", "\t"],
                    $inner
                );
                $parts[] = $inner;
            }
        }

        return implode(' ', $parts);
    }

    private static function fromDocx(string $path): string
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Extensão ZIP do PHP não disponível para ler DOCX.');
        }

        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml) || $xml === '') {
            return '';
        }

        $xml = preg_replace('/<w:tab[^>]*\/>/', "\t", $xml) ?? $xml;
        $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
        $text = strip_tags($xml);

        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
