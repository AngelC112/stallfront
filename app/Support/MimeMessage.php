<?php

namespace App\Support;

/**
 * Lettura di un messaggio MIME (formato .eml) per mostrarlo nel pannello:
 * intestazioni, testo, HTML e allegati, comprese le immagini interne ("cid:").
 */
final class MimeMessage
{
    /**
     * @param  array<string, string>  $headers  Intestazioni grezze, con nome in minuscolo.
     * @param  list<array{filename: string, content_type: string, content: string, content_id: ?string, inline: bool}>  $attachments
     */
    private function __construct(
        private array $headers,
        public readonly ?string $html,
        public readonly ?string $text,
        public readonly array $attachments,
    ) {}

    public static function parse(string $raw): self
    {
        [$headers, $body] = self::splitPart($raw);

        $html = null;
        $text = null;
        $attachments = [];
        self::walk($headers, $body, $html, $text, $attachments);

        return new self($headers, $html, $text, $attachments);
    }

    /**
     * Valore di un'intestazione, già decodificato (es. oggetti con accenti).
     */
    public function header(string $name): ?string
    {
        $value = $this->headers[strtolower($name)] ?? null;

        return $value === null ? null : self::decodeHeader($value);
    }

    /**
     * HTML con le immagini interne sostituite da data URI, così si vedono anche fuori dal client di posta.
     */
    public function htmlWithInlineImages(): ?string
    {
        if ($this->html === null) {
            return null;
        }

        $replacements = [];

        foreach ($this->attachments as $attachment) {
            if ($attachment['content_id'] !== null) {
                $replacements['cid:'.$attachment['content_id']] = 'data:'.$attachment['content_type'].';base64,'.base64_encode($attachment['content']);
            }
        }

        return strtr($this->html, $replacements);
    }

    /**
     * @return array{0: array<string, string>, 1: string}
     */
    private static function splitPart(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $separator = strpos($raw, "\n\n");
        $headerBlock = $separator === false ? $raw : substr($raw, 0, $separator);
        $body = $separator === false ? '' : substr($raw, $separator + 2);

        $headers = [];
        $currentName = null;

        foreach (explode("\n", $headerBlock) as $line) {
            if ($currentName !== null && ($line !== '' && ($line[0] === ' ' || $line[0] === "\t"))) {
                $headers[$currentName] .= ' '.trim($line);

                continue;
            }

            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $name = strtolower(trim($name));

                if (! array_key_exists($name, $headers)) {
                    $headers[$name] = trim($value);
                    $currentName = $name;
                } else {
                    $currentName = null;
                }
            }
        }

        return [$headers, $body];
    }

    /**
     * @param  array<string, string>  $headers
     * @param  list<array{filename: string, content_type: string, content: string, content_id: ?string, inline: bool}>  $attachments
     */
    private static function walk(array $headers, string $body, ?string &$html, ?string &$text, array &$attachments): void
    {
        [$contentType, $typeParams] = self::parseHeaderValue($headers['content-type'] ?? 'text/plain');

        if (str_starts_with($contentType, 'multipart/')) {
            $boundary = $typeParams['boundary'] ?? null;

            if ($boundary === null) {
                return;
            }

            foreach (self::splitMultipart($body, $boundary) as $part) {
                [$partHeaders, $partBody] = self::splitPart($part);
                self::walk($partHeaders, $partBody, $html, $text, $attachments);
            }

            return;
        }

        [$disposition, $dispositionParams] = self::parseHeaderValue($headers['content-disposition'] ?? '');
        $filename = $dispositionParams['filename'] ?? $typeParams['name'] ?? null;
        $content = self::decodeBody($body, strtolower(trim($headers['content-transfer-encoding'] ?? '')));
        $isText = $contentType === 'text/plain' || $contentType === 'text/html';

        if ($isText && $disposition !== 'attachment' && $filename === null) {
            $content = self::toUtf8($content, $typeParams['charset'] ?? 'utf-8');

            if ($contentType === 'text/html' && $html === null) {
                $html = $content;

                return;
            }

            if ($contentType === 'text/plain' && $text === null) {
                $text = $content;

                return;
            }
        }

        $contentId = isset($headers['content-id']) ? trim($headers['content-id'], ' <>') : null;

        $attachments[] = [
            'filename' => $filename !== null ? self::decodeHeader($filename) : 'allegato-'.(count($attachments) + 1),
            'content_type' => $contentType,
            'content' => $content,
            'content_id' => $contentId !== '' ? $contentId : null,
            'inline' => $disposition === 'inline',
        ];
    }

    /**
     * @return list<string>
     */
    private static function splitMultipart(string $body, string $boundary): array
    {
        $parts = [];
        $delimiter = '--'.$boundary;

        foreach (explode("\n".$delimiter, "\n".$body) as $index => $chunk) {
            if ($index === 0) {
                continue;
            }

            if (str_starts_with($chunk, '--')) {
                break;
            }

            $parts[] = ltrim(substr($chunk, (int) strpos($chunk."\n", "\n") + 1), "\r");
        }

        return $parts;
    }

    /**
     * Separa valore e parametri, gestendo anche i parametri RFC 2231 (filename*=utf-8''... e spezzati in *0, *1).
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private static function parseHeaderValue(string $value): array
    {
        $segments = preg_split('/;(?=(?:[^"]*"[^"]*")*[^"]*$)/', $value) ?: [];
        $main = strtolower(trim((string) array_shift($segments)));
        $params = [];
        $continuations = [];

        foreach ($segments as $segment) {
            if (! str_contains($segment, '=')) {
                continue;
            }

            [$name, $paramValue] = explode('=', $segment, 2);
            $name = strtolower(trim($name));
            $paramValue = trim($paramValue);

            if (str_starts_with($paramValue, '"') && str_ends_with($paramValue, '"')) {
                $paramValue = stripcslashes(substr($paramValue, 1, -1));
            }

            if (preg_match('/^([a-z0-9_-]+)\*(\d+)?(\*)?$/', $name, $matches)) {
                $encoded = isset($matches[3]) || ! isset($matches[2]) || $matches[2] === '';
                $continuations[$matches[1]][(int) ($matches[2] ?? 0)] = [$paramValue, $encoded];

                continue;
            }

            $params[$name] = $paramValue;
        }

        foreach ($continuations as $name => $pieces) {
            ksort($pieces);
            $charset = 'utf-8';
            $joined = '';

            foreach ($pieces as $position => [$piece, $encoded]) {
                if ($encoded && $position === array_key_first($pieces) && substr_count($piece, "'") >= 2) {
                    [$charset, , $piece] = explode("'", $piece, 3);
                }

                $joined .= $encoded ? rawurldecode($piece) : $piece;
            }

            $params[$name] = self::toUtf8($joined, $charset ?: 'utf-8');
        }

        return [$main, $params];
    }

    private static function decodeBody(string $body, string $encoding): string
    {
        return match ($encoding) {
            'base64' => (string) base64_decode((string) preg_replace('/\s+/', '', $body)),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    private static function decodeHeader(string $value): string
    {
        // Senza parole codificate (=?...?=) il valore è già testo leggibile: iconv toglierebbe gli accenti.
        if (! str_contains($value, '=?')) {
            return $value;
        }

        $decoded = iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded === false ? $value : $decoded;
    }

    private static function toUtf8(string $content, string $charset): string
    {
        $charset = strtolower(trim($charset, " \"'"));

        if ($charset === '' || $charset === 'utf-8' || $charset === 'us-ascii') {
            return $content;
        }

        try {
            return mb_convert_encoding($content, 'UTF-8', $charset);
        } catch (\ValueError) {
            return $content;
        }
    }
}
