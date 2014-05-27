<?php

/**
 * Hoa
 *
 *
 * @license
 *
 * New BSD License
 *
 * Copyright © 2007-2014, Ivan Enderlin. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Hoa nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace {

from('Hoa')

/**
 * \Hoa\Socket\Server
 */
-> import('Socket.Server')

/**
 * \Hoa\Socket\Client
 */
-> import('Socket.Client')

/**
 * \Hoa\Fastcgi\Responder
 */
-> import('Fastcgi.Responder')

/**
 * \Hoa\File\Read
 */
-> import('File.Read')

/**
 * \Hoa\File\Finder
 */
-> import('File.Finder')

/**
 * \Hoa\Http\Request
 */
-> import('Http.Request')

/**
 * \Hoa\Mime
 */
-> import('Mime.~');

}

namespace Hoa\Http\Bin {

/**
 * Class \Hoa\Http\Bin\Bhoa.
 *
 * A damn stupid and very very simple HTTP server (just for fun).
 *
 * @author     Ivan Enderlin <ivan.enderlin@hoa-project.net>
 * @copyright  Copyright © 2007-2014 Ivan Enderlin.
 * @license    New BSD License
 */

class Bhoa extends \Hoa\Console\Dispatcher\Kit {

    /**
     * Options description.
     *
     * @var \Hoa\Http\Bin\Bhoa array
     */
    protected $options = array(
        array('listen',       \Hoa\Console\GetOption::REQUIRED_ARGUMENT, 'l'),
        array('fastcgi',      \Hoa\Console\GetOption::REQUIRED_ARGUMENT, 'f'),
        array('root',         \Hoa\Console\GetOption::REQUIRED_ARGUMENT, 'r'),
        array('print-buffer', \Hoa\Console\GetOption::NO_ARGUMENT,       'b'),
        array('help',         \Hoa\Console\GetOption::NO_ARGUMENT,       'h'),
        array('help',         \Hoa\Console\GetOption::NO_ARGUMENT,       '?')
    );



    /**
     * The entry method.
     *
     * @access  public
     * @return  int
     */
    public function main ( ) {

        $listen  = '127.0.0.1:8888';
        $fastcgi = '127.0.0.1:9000';
        $root    = '.';
        $pbuffer = false;

        while(false !== $c = $this->getOption($v)) switch($c) {

            case 'l':
                $listen = $v;
              break;

            case 'f':
                $fastcgi = $v;
              break;

            case 'r':
                $root = $v;
              break;

            case 'b':
                $pbuffer = !$pbuffer;
              break;

            case 'h':
            case '?':
                return $this->usage();
              break;

            case '__ambiguous':
                $this->resolveOptionAmbiguity($v);
              break;
        }

        $server   = new \Hoa\Socket\Server('tcp://' . $listen);
        $client   = new \Hoa\Fastcgi\Responder(
            new \Hoa\Socket\Client('tcp://' . $fastcgi)
        );
        $server->considerRemoteAddress(true);
        $server->connectAndWait();
        $request  = new \Hoa\Http\Request();
        $_root    = $root;
        $time     = time();
        $_headers = array(
            'GATEWAY_INTERFACE' => 'FastCGI/1.0',

            'SERVER_SOFTWARE'   => 'Hoa+Bhoa/0.1',
            'SERVER_PROTOCOL'   => 'HTTP/1.1',
            'SERVER_NAME'       => $server->getSocket()->getAddress() . ':' .
                                   $server->getSocket()->getPort(),
            'SERVER_ADDR'       => $server->getSocket()->getAddress(),
            'SERVER_PORT'       => $server->getSocket()->getPort(),
            'SERVER_SIGNATURE'  => 'Hoa+Bhoa/0.1 \o/, PHP/' . phpversion(),
            'HTTP_HOST'         => null,

            'REQUEST_METHOD'    => null,
            'REQUEST_URI'       => null,
            'REQUEST_TIME'      => 0,

            'SCRIPT_FILENAME'   => null,
            'SCRIPT_NAME'       => null
        );

        if('hoa://' == substr($_root, 0, 6))
            $_root = resolve($_root);
        else {
            $root  = realpath($root);
            $_root = $root . DS;
        }

        echo 'Server is up, on ' . $server->getSocket() . '!', "\n",
             'Root: ' . $root . '.', "\n\n";

        $this->log('Waiting for connection…');

        while(true) foreach($server->select() as $node) {

            $buffer = $server->read(2048);

            if(empty($buffer)) {

                $server->disconnect();

                continue;
            }

            if(true === $pbuffer) {

                $this->log("\r");
                $this->log(null);
                $this->log("\r");
                var_dump($buffer);
            }

            $request->parse($buffer);
            $method                  = $request->getMethod();
            $methodReadable          = strtoupper($method);
            $uri                     = $request->getUrl();
            $uri                     = substr($uri, 0, strpos($uri, '?') ?: strlen($uri));
            $url                     = ltrim($uri, '/');
            $ttime                   = time();
            $smartPrint              = "\r";
            $_headers['HTTP_HOST']   = $request['host'];
            $_headers['REMOTE_ADDR'] = $server->getRemoteAddress();

            if($ttime - $time >= 2) {

                $this->log("\r");
                $smartPrint = "\n";
            }

            $this->log(
                $smartPrint . '↺ '. $methodReadable . ' /' . $url .
                ' (waiting…)'
            );

            $time   = $ttime;
            $target = $_root . $url;

            if(true === file_exists($target)) {

                // Listing.
                if(true === is_dir($target)) {

                    if(file_exists($_root . $url . DS . 'index.php')) {

                        $target = $_root . $url . DS . 'index.php';
                        $url    = 'index.php';
                    }
                    elseif(file_exists($_root . $url . DS . 'index.html')) {

                        $target = $_root . $url . DS . 'index.html';
                        $url    = 'index.html';
                    }
                    else {

                        $content = $request['host'] . '/' . $url . "\n" .
                                   str_repeat(
                                       '*',
                                       strlen($request['host']) + 1
                                   ) . "\n\n";
                        $finder  = new \Hoa\File\Finder();
                        $finder->in($target)
                               ->maxDepth(1);

                        foreach($finder as $_file) {

                            $defined  = $_file->open();
                            $content .= sprintf(
                                '%10d %s %s %s  %s',
                                $defined->getINode(),
                                $defined->getReadablePermissions(),
                                $defined->getOwner(),
                                date('Y-m-d H:i', $defined->getMTime()),
                                $defined->getBasename()
                            ) . "\n";
                            $_file->close();
                        }

                        $content .= "\n\n" . str_repeat('_', 42) . "\n\n" .
                                    $_headers['SERVER_SIGNATURE'];

                        $server->writeAll(
                            'HTTP/1.1 200 OK' . "\r\n" .
                            'Date: ' . date('r') . "\r\n" .
                            'Server: Hoa+Bhoa/0.1' . "\r\n" .
                            'Content-Type: text/plain' . "\r\n" .
                            'Content-Length: ' . strlen($content) . "\r\n\r\n" .
                            $content
                        );

                        $this->log("\r" . '✔ '. $methodReadable . ' /' . $url);

                        $this->log(null);
                        $this->log("\n" . 'Waiting for new connection…');

                        continue;
                    }
                }

                $file = new \Hoa\File\Read($target);

                // Static.
                if('php' !== $file->getExtension()) {

                    try {

                        $mime     = new \Hoa\Mime($file);
                        $mimeType = $mime->getMime();
                    }
                    catch ( \Hoa\Mime\Exception\MimeIsNotFound $e ) {

                        $mimeType = 'application/octet-stream';
                    }

                    $server->writeAll(
                        'HTTP/1.1 200 OK' . "\r\n" .
                        'Date: ' . date('r') . "\r\n" .
                        'Server: Hoa+Bhoa/0.1' . "\r\n" .
                        'Content-Type: ' . $mimeType . "\r\n" .
                        'Content-Length: ' . $file->getSize() . "\r\n\r\n" .
                        $file->readAll()
                    );

                    $this->log("\r" . '✔ '. $methodReadable . ' /' . $url);

                    $this->log(null);
                    $this->log("\n" . 'Waiting for new connection…');

                    continue;
                }

                $script_filename = $target;
                $script_name     = DS . $url;
            }

            $pathinfo = pathinfo($target);

            if('php' != $pathinfo['extension']) {

                $script_filename = $_root . 'index.php';
                $script_name     = DS . 'index.php';
            }

            if(   !isset($script_filename)
               || false === file_exists($script_filename)) {

                $content = '404 Not Found :-\'(';
                $server->writeAll(
                    'HTTP/1.1 404 Not Found' . "\r\n" .
                    'Date: ' . date('r') . "\r\n" .
                    'Server: Hoa+Bhoa/0.1' . "\r\n" .
                    'Content-Type: text/plain' . "\r\n" .
                    'Content-Length: ' . strlen($content) . "\r\n\r\n" .
                    $content
                );

                $this->log("\r" . '✔ '. $methodReadable . ' /' . $url);

                $this->log(null);
                $this->log("\n" . 'Waiting for new connection…');

                continue;
            }

            switch($method) {

                case \Hoa\Http\Request::METHOD_GET:
                    $data    = null;
                    $headers = array_merge(
                        $_headers,
                        $request->getHeadersFormatted(),
                        array(
                            'REQUEST_METHOD'  => 'GET',
                            'REQUEST_URI'     => DS . $uri,
                            'REQUEST_TIME'    => time(),
                            'SCRIPT_FILENAME' => $script_filename,
                            'SCRIPT_NAME'     => $script_name
                        )
                    );
                  break;

                case \Hoa\Http\Request::METHOD_POST:
                    $data = $request->getBody();

                    switch(strtolower($request['content-type'])) {

                        case 'application/json':
                            $data = http_build_query(@json_decode($data) ?: array());
                          break;
                    }

                    $headers = array_merge(
                        $_headers,
                        $request->getHeadersFormatted(),
                        array(
                            'REQUEST_METHOD'  => 'POST',
                            'REQUEST_URI'     => DS . $uri,
                            'REQUEST_TIME'    => time(),
                            'SCRIPT_FILENAME' => $script_filename,
                            'SCRIPT_NAME'     => $script_name,
                            'CONTENT_TYPE'    => 'application/x-www-form-urlencoded',
                            'CONTENT_LENGTH'  => strlen($data)
                        )
                    );
                  break;

                default:
                    $content = 'This server is stupid and does not ' .
                               'support ' . $methodReadable . '! ' .
                               'Yup, damn stupid…';
                    $server->writeAll(
                        'HTTP/1.1 200 OK' . "\r\n" .
                        'Date: ' . date('r') . "\r\n" .
                        'Server: Hoa+Bhoa/0.1' . "\r\n" .
                        'Content-Type: text/html' . "\r\n" .
                        'Content-Length: ' . strlen($content) . "\r\n\r\n" .
                        $content
                    );
                  continue 2;
            }

            try {

                $content = $client->send($headers, $data);
            }
            catch ( \Hoa\Socket\Exception $ee ) {

                $socket  = $client->getClient()->getSocket();
                $listen  = $socket->getAddress() . ':' .
                           $socket->getPort();
                $this->log("\r" . '✖ ' . $methodReadable . ' /' . $url);
                $this->log("\n" . '  ↳ PHP FastCGI seems to be ' .
                           'disconnected (tried to reach ' . $socket .
                           ').' . "\n" .
                           '  ↳ Try $ php-cgi -b ' . $listen . "\n" .
                           '     or $ php-fpm -d listen=' . $listen . "\n");
                $this->log(null);

                continue;
            }

            $response  = $client->getResponseHeaders();
            $headers   = array_merge(
                array(
                    'date'           => date('r'),
                    'content-length' => strlen($content)
                ),
                $response
            );
            $_response = null;

            foreach($headers as $name => $value)
                $_response .= $name . ': ' . $value . "\r\n";

            $server->writeAll(
                'HTTP/1.1 200 OK' . "\r\n" .
                'server: Hoa+Bhoa/0.1' . "\r\n" .
                $_response . "\r\n" .
                $content
            );

            $this->log("\r" . '✔ '. $methodReadable . ' /' . $url);
            $this->log(null);
            $this->log("\n" . 'Waiting for new connection…');
        }

        return;
    }

    /**
     * Just a server log :-). For fun only.
     *
     * @access  protected
     * @param   string     $message    Message.
     * @return  void
     */
    protected function log ( $message ) {

        static $l = 0;

        if(null === $message) {

            $l = 0;

            return;
        }

        $l = max($l, strlen($message) + 1);
        echo str_pad($message, $l, ' ', STR_PAD_RIGHT);

        return;
    }

    /**
     * The command usage.
     *
     * @access  public
     * @return  int
     */
    public function usage ( ) {

        echo 'Usage   : http:bhoa <options>', "\n",
             'Options :', "\n",
             $this->makeUsageOptionsList(array(
                 'l'    => 'Socket URI to listen (default: 127.0.0.1:8888).',
                 'f'    => 'PHP FastCGI or PHP-FPM socket URI (default: 127.0.0.1:9000).',
                 'r'    => 'Public/document root.',
                 'b'    => 'Print buffers (headers & content).',
                 'help' => 'This help.'
             )), "\n",
             'Bhoa needs PHP FastCGI to communicate with PHP.', "\n",
             'To start PHP FastCGI:', "\n",
             '    $ php-cgi -b 127.0.0.1:9000', "\n",
             'or', "\n",
             '    $ php-fpm -d listen=127.0.0.1:9000', "\n";

        return;
    }
}

}

__halt_compiler();
A damn stupid and very simple HTTP server.
