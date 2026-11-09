<?php

namespace App\Services;

use RuntimeException;

/**
 * Client IMAP minimo per salvare le email inviate nella cartella "Inviate" della casella del sito.
 *
 * Usa solo socket TLS: non richiede l'estensione imap di PHP.
 */
class ImapSentFolder
{
    private const DEFAULT_FOLDER = 'Sent Items';

    /** @var resource|null */
    private $stream = null;

    private int $tagCounter = 0;

    private ?string $resolvedFolder = null;

    /** @var list<array{line: string, data: string}> Literal ricevuti nell'ultima risposta, con la riga che li precede. */
    private array $lastLiterals = [];

    public function __construct(
        private string $host,
        private int $port,
        private string $username,
        private string $password,
        private ?string $folder = null,
        private int $timeoutSeconds = 20,
    ) {}

    public static function fromConfig(): self
    {
        $config = config('mail.sent_copy');

        return new self(
            (string) $config['host'],
            (int) $config['port'],
            (string) $config['username'],
            (string) $config['password'],
            $config['folder'] ?: null,
        );
    }

    public function connect(): void
    {
        $context = stream_context_create(['ssl' => ['peer_name' => $this->host]]);
        $stream = @stream_socket_client(
            "ssl://{$this->host}:{$this->port}",
            $errorCode,
            $errorMessage,
            $this->timeoutSeconds,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($stream === false) {
            throw new RuntimeException("IMAP connection failed: {$errorMessage} ({$errorCode})");
        }

        stream_set_timeout($stream, $this->timeoutSeconds);
        $this->stream = $stream;

        $greeting = $this->readLine();

        if (! str_starts_with($greeting, '* OK')) {
            throw new RuntimeException('Unexpected IMAP response: '.trim($greeting));
        }

        $this->command('LOGIN '.$this->quote($this->username).' '.$this->quote($this->password));
    }

    /**
     * Salva il messaggio MIME completo nella cartella "Inviate", già segnato come letto.
     */
    public function append(string $rawMessage, ?\DateTimeInterface $sentAt = null): void
    {
        $rawMessage = rtrim(preg_replace("/\r?\n/", "\r\n", $rawMessage) ?? $rawMessage, "\r\n")."\r\n";
        $date = $sentAt !== null ? ' "'.$sentAt->format('d-M-Y H:i:s O').'"' : '';
        $tag = $this->nextTag();

        $this->write("{$tag} APPEND ".$this->quote($this->sentFolder())." (\\Seen){$date} {".strlen($rawMessage)."}\r\n");

        $continuation = $this->readLine();

        if (! str_starts_with($continuation, '+')) {
            throw new RuntimeException('APPEND rifiutato: '.trim($continuation));
        }

        $this->write($rawMessage."\r\n");
        $this->readUntilTagged($tag);
    }

    /**
     * Messaggio MIME completo presente in "Inviate" con questo Message-ID, oppure null se non c'è.
     */
    public function fetchByMessageId(string $messageId, ?\DateTimeInterface $sentAt = null): ?string
    {
        $this->command('EXAMINE '.$this->quote($this->sentFolder()));

        // Non tutti i server cercano per Message-ID (Stalwart no): si filtra per data e si confrontano le intestazioni.
        $criteria = $sentAt !== null
            ? 'SINCE '.\DateTimeImmutable::createFromInterface($sentAt)->modify('-1 day')->format('d-M-Y')
            : 'ALL';
        $search = $this->command('UID SEARCH '.$criteria);

        preg_match('/^\* SEARCH([\d ]*)/mi', $search, $matches);
        $uids = array_reverse(array_values(array_filter(explode(' ', trim($matches[1] ?? '')))));
        $wanted = strtolower(trim($messageId, '<> '));

        foreach (array_chunk($uids, 200) as $chunk) {
            $this->command('UID FETCH '.implode(',', $chunk).' (UID BODY.PEEK[HEADER.FIELDS (MESSAGE-ID)])');

            foreach ($this->lastLiterals as $literal) {
                if (preg_match('/^Message-ID:\s*<?([^>\s]+)>?/mi', $literal['data'], $header)
                    && strtolower($header[1]) === $wanted
                    && preg_match('/\bUID (\d+)/i', $literal['line'], $uid)) {
                    $this->command('UID FETCH '.$uid[1].' BODY.PEEK[]');

                    return $this->lastLiterals[0]['data'] ?? null;
                }
            }
        }

        return null;
    }

    public function disconnect(): void
    {
        if ($this->stream === null) {
            return;
        }

        try {
            $this->command('LOGOUT');
        } catch (\Throwable) {
            // La chiusura della connessione va bene anche senza risposta.
        }

        fclose($this->stream);
        $this->stream = null;
    }

    /**
     * Cartella configurata, oppure quella con attributo \Sent, oppure "Sent Items".
     */
    private function sentFolder(): string
    {
        if ($this->resolvedFolder !== null) {
            return $this->resolvedFolder;
        }

        if ($this->folder !== null) {
            return $this->resolvedFolder = $this->folder;
        }

        $this->resolvedFolder = self::DEFAULT_FOLDER;

        foreach (explode("\n", $this->command('LIST "" "*"')) as $line) {
            if (preg_match('/^\* LIST \(([^)]*)\) \S+ (.+)$/i', trim($line), $matches) && stripos($matches[1], '\\Sent') !== false) {
                $this->resolvedFolder = stripcslashes(trim($matches[2], '"'));
                break;
            }
        }

        return $this->resolvedFolder;
    }

    private function command(string $command): string
    {
        $tag = $this->nextTag();
        $this->write("{$tag} {$command}\r\n");

        return $this->readUntilTagged($tag);
    }

    private function readUntilTagged(string $tag): string
    {
        $response = '';
        $this->lastLiterals = [];

        while (true) {
            $line = $this->readLine();
            $response .= $line;

            if (preg_match('/\{(\d+)\}\r?\n$/', $line, $literal)) {
                $this->lastLiterals[] = ['line' => $line, 'data' => $this->readBytes((int) $literal[1])];

                continue;
            }

            if (str_starts_with($line, $tag.' ')) {
                if (! str_starts_with($line, $tag.' OK')) {
                    throw new RuntimeException('IMAP command failed: '.trim(substr($line, strlen($tag) + 1)));
                }

                return $response;
            }
        }
    }

    private function readLine(): string
    {
        $line = $this->stream !== null ? fgets($this->stream) : false;

        if ($line === false) {
            throw new RuntimeException(__('Connessione IMAP interrotta o scaduta.'));
        }

        return $line;
    }

    private function readBytes(int $length): string
    {
        $data = '';

        while (strlen($data) < $length) {
            $chunk = $this->stream !== null ? fread($this->stream, min(65536, $length - strlen($data))) : false;

            if ($chunk === false || $chunk === '') {
                throw new RuntimeException(__('Connessione IMAP interrotta durante la lettura del messaggio.'));
            }

            $data .= $chunk;
        }

        return $data;
    }

    private function write(string $data): void
    {
        if ($this->stream === null || fwrite($this->stream, $data) === false) {
            throw new RuntimeException(__('Scrittura sulla connessione IMAP non riuscita.'));
        }
    }

    private function nextTag(): string
    {
        return 'V'.(++$this->tagCounter);
    }

    private function quote(string $value): string
    {
        if (preg_match('/[\r\n]/', $value)) {
            throw new RuntimeException(__('Valore IMAP non valido.'));
        }

        return '"'.addcslashes($value, '"\\').'"';
    }
}
