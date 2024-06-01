<?php declare(strict_types=1);

/**
 * This file is part of Reymon.
 * Reymon is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * Reymon is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    AhJ <AmirHosseinJafari8228@gmail.com>
 * @copyright 2023-2024 AhJ <AmirHosseinJafari8228@gmail.com>
 * @license   https://choosealicense.com/licenses/gpl-3.0/ GPLv3
 */

namespace Reymon\Ipc;

use Amp\ByteStream\StreamChannel;
use Amp\Cancellation;
use Amp\NullCancellation;
use Amp\Socket\Socket;
use Amp\Sync\ChannelException;
use Closure;

final class IpcConnection
{
    private StreamChannel $channel;

    public function __construct(private Socket $socket, private ?Cancellation $cancellation = null)
    {
        $this->channel = new StreamChannel($socket, $socket);
        $this->cancellation ??= new NullCancellation;
    }

    public function getCancelation(): Cancellation
    {
        return $this->cancellation;
    }

    public function send(mixed $data): void
    {
        $this->channel->send($data);
    }

    public function receive(): mixed
    {
        try {
            return $this->channel->receive();
        } catch (ChannelException $e) {
            if ($e->getMessage() === 'The channel closed while waiting to receive the next value') {
                return null;
            }
            throw $e;
        }
    }

    public function close(): void
    {
        $this->channel->close();
    }

    /**
     * Closes the read and write resource streams.
     */
    public function disconnect(): void
    {
        if (!$this->isClosed()) {
            $this->send(null);
            $this->channel->close();
        }
    }

    public function isClosed(): bool
    {
        return $this->channel->isClosed();
    }

    public function onClose(Closure $onClose): void
    {
        $this->channel->onClose($onClose);
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
