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
use Amp\Socket\Socket;
use Amp\Socket\SocketAddress;
use Amp\Socket\SocketConnector;
use Closure;
use function Amp\Parallel\Ipc\connect;

final class Client
{
    private Connection $connection;
    private Socket $socket;

    public function __construct(SocketAddress|string $address, string $key, ?Cancellation $cancellation = null, ?SocketConnector $connector = null)
    {
        $this->socket  = connect($address, $key, $cancellation, $connector);
        $this->connection = new Connection($this->socket, $cancellation);
    }

    public function send(mixed $data): void
    {
        $this->connection->send($data);
    }

    public function receive(): mixed
    {
        return $this->connection->receive();
    }

    public function disconnect(): void
    {
        if (!$this->isClosed()) {
            $this->send(null);
            $this->connection->disconnect();
        }
    }

    public function isClosed(): bool
    {
        return $this->connection->isClosed();
    }

    public function onClose(Closure $onClose): void
    {
        $this->connection->onClose($onClose);
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
