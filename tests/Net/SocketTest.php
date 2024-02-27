<?php

namespace Tests\Net;

use Concurrent\Worker\InterruptibleProcess;
use Util\Net\{
    Socket,
    ServerSocket
};
use PHPUnit\Framework\TestCase;
use Swoole\Coroutine\Client;
use function \Swoole\Coroutine\run;

class SocketTest extends TestCase
{
    public function testMethods(): void
    {
        $p0 = new InterruptibleProcess(function ($process) {
            $server = new \Swoole\Process(function ($process) {
                $server = new ServerSocket(1081);
                $readClients = [];
                $readData = [];
                while ($client = $server->accept()) { //  stream_socket_accept($server)
                    $isWriteClient = false;
                    while ($read = $client->read(8192)) { //    // stream_socket_recvfrom($client, 8192)
                        if (strpos($read, "w") === false) {
                            $isWriteClient = true;
                            $readData[] = $read;
                        } else {
                            break;
                        }
                    }
                    if (!$isWriteClient) {
                        $readClients[] = $client;
                    }
                    foreach ($readClients as $readClient) {
                        foreach ($readData as $pid) {
                            //stream_socket_sendto($readClient, $pid);                            
                            $readClient->write($pid);
                        }
                    }
                }
            });
            $server->start();       
        });
        $p0->start();

        $p2 = new InterruptibleProcess(function ($process) {
            $s = new Socket("localhost", 1081);
            $message = "w" . $process->pid. " ";
            $s->write($message, strlen($message));
            while($r = $s->read(8192)) {
                $notifications = explode(" ", $r);
                foreach ($notifications as $notification) {
                    if (is_numeric($notification) && intval($notification) == $process->pid) {
                        fwrite(STDERR, 'PID: ' . $notification ."\n");
                        $s->close();
                        break(2);
                    }
                }
            }            
        });
        $p2->start();

        $pid = $p2->pid;
  
        $p1 = new InterruptibleProcess(function ($process) use ($pid) {
            $s = new Socket("localhost", 1081);
            $message = $pid . ' ';
            $s->write($message, strlen($message));
            $s->close();
        });
        $p1->start();

        $p4 = new InterruptibleProcess(function ($process) {
            $s = new Socket("localhost", 1081);
            $message = "w" . $process->pid . " ";
            $s->write($message, strlen($message));
            while($r = $s->read(8192)) {
                $notifications = explode(" ", $r);
                foreach ($notifications as $notification) {
                    if (is_numeric($notification) && intval($notification) == $process->pid) {
                        fwrite(STDERR, 'PID: ' . $notification ."\n");
                        $s->close();
                        break(2);
                    }
                }
            }            
        });
        $p4->start();
        
        $pid = $p4->pid;
  
        $p5 = new InterruptibleProcess(function ($process) use ($pid) {
            $s = new Socket("localhost", 1081);
            $message = $pid . ' ';
            $s->write($message, strlen($message));
            $s->close();
        });
        $p5->start();

        $p0->wait();
        $p1->wait();
        $p2->wait();
        $p4->wait();
        $p5->wait();



        /*$p1 = new \Swoole\Process(function () {
            sleep(30);
            $server = stream_socket_server("tcp://localhost:1081");
            while ($client = stream_socket_accept($server)) {
                fwrite(STDERR, "=== Client Connected ===\n");
            }
        });
        $p1->start();

        $p2 = new \Swoole\Process(function () {
            $client = stream_socket_client("tcp://localhost:1081", $errorNo, $errorStr, 60000);
            fwrite(STDERR, "Connection status: " . socket_strerror(socket_last_error()) . ", last error: $errorNo, $errorStr\n");
            stream_socket_sendto($client, "=== Hello world ===");
            stream_socket_shutdown($client, STREAM_SHUT_WR);
        });
        $p2->start();

        $p1->wait();
        $p2->wait();*/

    }
}
