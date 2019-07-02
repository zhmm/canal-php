<?php
namespace client;

class TcpClient
{

    /**
     * Handle to PHP socket
     *
     * @var resource
     */
    private $handle_ = null;

    /**
     * Remote hostname
     *
     * @var string
     */
    protected $host_ = 'localhost';

    /**
     * Remote port
     *
     * @var int
     */
    protected $port_ = '9090';

    /**
     * Send timeout in milliseconds
     *
     * @var int
     */
    private $sendTimeout_ = 100;

    /**
     * Recv timeout in milliseconds
     *
     * @var int
     */
    private $recvTimeout_ = 750;
    
    /**
     * connect time out
     * @var unknown
     */
    private $connectTimeout_ = 1000;

    /**
     * Is send timeout set?
     *
     * @var bool
     */
    private $sendTimeoutSet_ = false;

    /**
     * Persistent socket or plain?
     *
     * @var bool
     */
    private $persist_ = false;

    /**
     * Socket constructor
     *
     * @param string $host
     *            Remote hostname
     * @param int $port
     *            Remote port
     * @param bool $persist
     *            Whether to use a persistent socket
     */
    public function __construct($host = 'localhost', $port = 9090, $persist = false)
    {
        $this->host_ = $host;
        $this->port_ = $port;
        $this->persist_ = $persist;
    }

    /**
     *
     * @param resource $handle
     * @return void
     */
    public function setHandle($handle)
    {
        $this->handle_ = $handle;
    }

    /**
     * Sets the send timeout.
     *
     * @param int $timeout
     *            Timeout in milliseconds.
     */
    public function setSendTimeout($timeout)
    {
        $this->sendTimeout_ = $timeout;
    }

    /**
     * Sets the receive timeout.
     *
     * @param int $timeout
     *            Timeout in milliseconds.
     */
    public function setRecvTimeout($timeout)
    {
        $this->recvTimeout_ = $timeout;
    }

    /**
     * @return unknown
     */
    public function getConnectTimeout_()
    {
        return $this->connectTimeout_;
    }

    /**
     * @param $connectTimeout_
     */
    public function setConnectTimeout_($connectTimeout_)
    {
        $this->connectTimeout_ = $connectTimeout_;
    }

    /**
        * Get the host that this socket is connected to
        *
        * @return string host
        */
    public function getHost()
    {
        return $this->host_;
    }

    /**
     * Get the remote port that this socket is connected to
     *
     * @return int port
     */
    public function getPort()
    {
        return $this->port_;
    }

    /**
     * Tests whether this is open
     *
     * @return bool true if the socket is open
     */
    public function isOpen()
    {
        return is_resource($this->handle_);
    }

    /**
     * Connects the socket.
     */
    public function open()
    {
        if ($this->isOpen()) {
            throw new \Exception('Socket already connected');
        }

        if (empty($this->host_)) {
            throw new \Exception('Cannot open null host');
        }
        
        if ($this->port_ <= 0) {
            throw new \Exception('Cannot open without port');
        }

        if ($this->persist_) {
            $this->handle_ = @pfsockopen($this->host_, $this->port_, $errno, $errstr, $this->connectTimeout_ / 1000.0);
        } else {
            $this->handle_ = @fsockopen($this->host_, $this->port_, $errno, $errstr, $this->connectTimeout_ / 1000.0);
        }

        // Connect failed?
        if ($this->handle_ === false) {
            $error = 'Socket: Could not connect to ' . $this->host_ . ':' . $this->port_ . ' (' . $errstr . ' [' . $errno . '])';
            throw new \Exception($error, 10);
        }

        stream_set_timeout($this->handle_, 0, $this->sendTimeout_);
        $this->sendTimeoutSet_ = true;
    }

    /**
     * Closes the socket.
     */
    public function close()
    {
        if (! $this->persist_) {
            @fclose($this->handle_);
            $this->handle_ = null;
        }
    }

    /**
     * Uses stream get contents to do the reading
     *
     * @param $len How many bytes
     * @return string Binary data
     * @throws \Exception
     */
    public function readAll($len)
    {
        if ($this->sendTimeoutSet_) {
            stream_set_timeout($this->handle_, 0, $this->recvTimeout_ * 1000);
            $this->sendTimeoutSet_ = false;
        }
        // This call does not obey stream_set_timeout values!
        // $buf = @stream_get_contents($this->handle_, $len);

        $pre = null;
        while (true) {
            $buf = @fread($this->handle_, $len);
            if ($buf === false || $buf === '') {
                $md = stream_get_meta_data($this->handle_);
                if ($md['timed_out']) {
                    throw new \Exception('TSocket: timed out reading ' . $len . ' bytes from ' . $this->host_ . ':' . $this->port_);
                } else {
                    throw new \Exception('TSocket: Could not read ' . $len . ' bytes from ' . $this->host_ . ':' . $this->port_);
                }
            } elseif (($sz = strlen($buf)) < $len) {
                $md = stream_get_meta_data($this->handle_);
                if ($md['timed_out']) {
                    throw new \Exception('TSocket: timed out reading ' . $len . ' bytes from ' . $this->host_ . ':' . $this->port_);
                } else {
                    $pre .= $buf;
                    $len -= $sz;
                }
            } else {
                return $pre . $buf;
            }
        }
    }

    /**
     * Read from the socket
     *
     * @param int $len
     *            How many bytes
     * @return string Binary data
     */
    public function read($len)
    {
        if ($this->sendTimeoutSet_) {
            stream_set_timeout($this->handle_, 0, $this->recvTimeout_ * 1000);
            $this->sendTimeoutSet_ = false;
        }
        $data = fread($this->handle_, $len);
        if ($data === false || $data === '') {
            $md = stream_get_meta_data($this->handle_);
            if ($md['timed_out']) {
                throw new \Exception('TSocket: timed out reading ' . $len . ' bytes from ' . $this->host_ . ':' . $this->port_);
            } else {
                throw new \Exception('TSocket: Could not read ' . $len . ' bytes from ' . $this->host_ . ':' . $this->port_);
            }
        }
        return $data;
    }

    /**
     * @param $buf
     * @throws \Exception
     */
    public function write($buf)
    {
        if (! $this->sendTimeoutSet_) {
            stream_set_timeout($this->handle_, 0, $this->sendTimeout_ * 1000);
            $this->sendTimeoutSet_ = true;
        }
        while (strlen($buf) > 0) {
            $got = @fwrite($this->handle_, $buf);
            if ($got === 0 || $got === false) {
                $md = stream_get_meta_data($this->handle_);
                if ($md['timed_out']) {
                    throw new \Exception('TSocket: timed out writing ' . strlen($buf) . ' bytes from ' . $this->host_ . ':' . $this->port_);
                } else {
                    throw new \Exception('TSocket: Could not write ' . strlen($buf) . ' bytes ' . $this->host_ . ':' . $this->port_);
                }
            }
            $buf = substr($buf, $got);
        }
    }

    /**
     * Flush output to the socket.
     */
    public function flush()
    {
        $ret = fflush($this->handle_);
        if ($ret === false) {
            throw new \Exception('TSocket: Could not flush: ' . $this->host_ . ':' . $this->port_);
        }
    }
}
