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
                $server = new ServerSocket(1085);
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
            $s = new Socket("localhost", 1085);
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
            $s = new Socket("localhost", 1085);
            $message = $pid . ' ';
            $s->write($message, strlen($message));
            $s->close();
        });
        $p1->start();

        $p4 = new InterruptibleProcess(function ($process) {
            $s = new Socket("localhost", 1085);
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
            $s = new Socket("localhost", 1085);
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
    }
}
