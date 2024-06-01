<?php declare(strict_types=1);

/**
 * This file is part of Reymon.
 * Reymon is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * Reymon is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Mahdi <mahdi.talaee1379@gmail.com>
 * @copyright 2023-2024 Mahdi <mahdi.talaee1379@gmail.com>
 * @license   https://choosealicense.com/licenses/gpl-3.0/ GPLv3
 */

namespace Reymon\Ipc;

use Amp\Cancellation;
use Amp\File\FilesystemException;
use Amp\NullCancellation;
use Amp\Parallel\Ipc\SocketIpcHub;
use Amp\Socket\InternetAddress;
use Amp\Socket\SocketAddress;
use Amp\Socket\SocketAddressType;
use Amp\Socket\UnixAddress;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Revolt\EventLoop;
use const Amp\Process\IS_WINDOWS;
use function Amp\File\deleteFile;
use function Amp\Socket\listen;

final class Server
{
    private ?string $toUnlink = null;
    private readonly SocketIpcHub $delegate;

    public function __construct(
        private SocketAddress|string|null $address = null,         // Socket address
        private ?string $key = null,                               // Key for connection
        private Cancellation $cancellation = new NullCancellation, // Cancellation
        private LoggerInterface $logger = new NullLogger,          // Logger interface
    ) {
        $this->key ??= $this->generateKey();
        if ($address === null) {
            if (IS_WINDOWS) {
                $address = new InternetAddress('127.0.0.1', 0);
            } else {
                $suffix = \bin2hex(\random_bytes(10));
                $path = \sys_get_temp_dir() . "/amp-parallel-ipc-" . $suffix . ".sock";
                $address = new UnixAddress($path);
            }
        }
        $socket = listen($address);
        // Unlink unix file after stoping server
        if ($socket->getAddress()->getType() === SocketAddressType::Unix) {
            $this->toUnlink = (string) $socket->getAddress()->toString();
        }
        $this->delegate = new SocketIpcHub($socket);
    }

    public function __destruct()
    {
        EventLoop::queue($this->delegate->close(...));
        $this->unlink();
    }

    public function accept(): IpcConnection
    {
        $socket = $this->delegate->accept($this->key, $this->cancellation);
        return new IpcConnection($socket, $this->cancellation);
    }

    /**
     * Returns whether this resource has been closed.
     *
     * @return bool {@code true} if closed, otherwise {@code false}
     */
    public function isClosed(): bool
    {
        return $this->delegate->isClosed();
    }

    public function close(): void
    {
        $this->delegate->close();
        $this->unlink();
    }

    public function onClose(\Closure $onClose): void
    {
        $this->delegate->onClose($onClose);
    }

    private function unlink(): void
    {
        if ($this->toUnlink === null) {
            return;
        }
        try {
            deleteFile($this->toUnlink);
        } catch (FilesystemException) {
        } finally {
            $this->toUnlink = null;
        }
    }

    public function getUri(): string
    {
        return $this->delegate->getUri();
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function generateKey(): string
    {
        return $this->delegate->generateKey();
    }
}
