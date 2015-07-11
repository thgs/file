<?php

namespace Amp\Fs;

use Amp\{ UvReactor, Promise, Failure, Deferred };
use function Amp\{ resolve, reactor };

class UvFilesystem implements Filesystem {
    private $reactor;
    private $loop;

    /**
     * @param \Amp\UvReactor $reactor
     */
    public function __construct(UvReactor $reactor) {
        $this->reactor = $reactor;
        $this->loop = $this->reactor->getUnderlyingLoop();
    }

    /**
     * {@inheritdoc}
     */
    public function open(string $path, int $mode = self::READ): Promise {
        $openFlags = 0;
        $fileChmod = 0;

        if ($mode & self::READ && $mode & self::WRITE) {
            $openFlags = \UV::O_RDWR;
            $mode |= self::CREATE;
        } elseif ($mode & self::READ) {
            $openFlags = \UV::O_RDONLY;
        } elseif ($mode & self::WRITE) {
            $openFlags = \UV::O_WRONLY;
            $mode |= self::CREATE;
        } else {
            return new Failure(new \InvalidArgumentException(
                "Invalid file open mode: Filesystem::READ or Filesystem::WRITE or both required"
            ));
        }

        if ($mode & self::CREATE) {
            $openFlags |= \UV::O_CREAT;
            $fileChmod = 0644;
        }

        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_open($this->loop, $path, $openFlags, $fileChmod, function($fh) use ($promisor) {
            $this->reactor->delRef();
            if ($fh) {
                $descriptor = new UvDescriptor($this->reactor, $fh);
                $promisor->succeed($descriptor);
            } else {
                $promisor->fail(new \RuntimeException(
                    "Failed opening file handle"
                ));
            }
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function stat(string $path): Promise {
        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_stat($this->loop, $path, function($fh, $stat) use ($promisor) {
            if ($fh) {
                $stat["isdir"] = (bool) ($stat["mode"] & \UV::S_IFDIR);
                $stat["isfile"] = empty($stat["isdir"]);
            } else {
                $stat = null;
            }
            $this->reactor->delRef();
            $promisor->succeed($stat);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function lstat(string $path): Promise {
        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_lstat($this->loop, $path, function($fh, $stat) use ($promisor) {
            if ($fh) {
                $stat["isdir"] = (bool) ($stat["mode"] & \UV::S_IFDIR);
                $stat["isfile"] = empty($stat["isdir"]);
            } else {
                $stat = null;
            }
            $this->reactor->delRef();
            $promisor->succeed($stat);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function symlink(string $target, string $link): Promise {
        $this->reactor->addRef();
        $promisor = new Deferred;
        uv_fs_symlink($this->loop, $target, $link, \UV::S_IRWXU | \UV::S_IRUSR, function($fh) use ($promisor) {
            $this->reactor->delRef();
            $promisor->succeed((bool)$fh);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function rename(string $from, string $to): Promise {
        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_rename($this->loop, $from, $to, function($fh) use ($promisor) {
            $this->reactor->delRef();
            $promisor->succeed((bool)$fh);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function unlink(string $path): Promise {
        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_unlink($this->loop, $path, function($fh) use ($promisor) {
            $this->reactor->delRef();
            $promisor->succeed((bool)$fh);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function mkdir(string $path, int $mode = 0644): Promise {
        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_mkdir($this->loop, $path, $mode, function($fh) use ($promisor) {
            $this->reactor->delRef();
            $promisor->succeed((bool)$fh);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function rmdir(string $path): Promise {
        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_rmdir($this->loop, $path, function($fh) use ($promisor) {
            $this->reactor->delRef();
            $promisor->succeed((bool)$fh);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function scandir(string $path): Promise {
        $this->reactor->addRef();
        $promisor = new Deferred;
        uv_fs_readdir($this->loop, $path, 0, function($fh, $data) use ($promisor, $path) {
            $this->reactor->delRef();
            if (empty($fh)) {
                $promisor->fail(new \RuntimeException(
                    "Failed reading contents from {$path}"
                ));
            } else {
                $promisor->succeed($data);
            }
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function chmod(string $path, int $mode): Promise {
        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_chmod($this->loop, $path, $mode, function($fh) use ($promisor) {
            $this->reactor->delRef();
            $promisor->succeed((bool)$fh);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function chown(string $path, int $uid, int $gid): Promise {
        // @TODO Return a failure in windows environments
        $this->reactor->addRef();
        $promisor = new Deferred;
        \uv_fs_chown($this->loop, $path, $uid, $gid, function($fh) use ($promisor) {
            $this->reactor->delRef();
            $promisor->succeed((bool)$fh);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $path): Promise {
        return resolve($this->doGet($path), $this->reactor);
    }

    private function doGet(string $path): \Generator {
        $this->reactor->addRef();
        if (!$fh = yield $this->doFsOpen($path, $flags = \UV::O_RDONLY, $mode = 0)) {
            $this->reactor->delRef();
            throw new \RuntimeException(
                "Failed opening file handle: {$path}"
            );
        }

        $promisor = new Deferred;
        $stat = yield $this->doFsStat($fh);
        if (empty($stat)) {
            $this->reactor->delRef();
            $promisor->fail(new \RuntimeException(
                "stat operation failed on open file handle"
            ));
        } elseif (!$stat["isfile"]) {
            \uv_fs_close($this->loop, $fh, function() use ($promisor) {
                $this->reactor->delRef();
                $promisor->fail(new \RuntimeException(
                    "cannot buffer contents: path is not a file"
                ));
            });
        } else {
            $buffer = yield $this->doFsRead($fh, $offset = 0, $stat["size"]);
            if ($buffer === false ) {
                \uv_fs_close($this->loop, $fh, function() use ($promisor) {
                    $this->reactor->delRef();
                    $promisor->fail(new \RuntimeException(
                        "read operation failed on open file handle"
                    ));
                });
            } else {
                \uv_fs_close($this->loop, $fh, function() use ($promisor, $buffer) {
                    $this->reactor->delRef();
                    $promisor->succeed($buffer);
                });
            }
        }

        return yield $promisor->promise();
    }

    private function doFsOpen(string $path, int $flags, int $mode): Promise {
        $promisor = new Deferred;
        \uv_fs_open($this->loop, $path, $flags, $mode, function($fh) use ($promisor, $path) {
            $promisor->succeed($fh);
        });

        return $promisor->promise();
    }

    private function doFsStat($fh): Promise {
        $promisor = new Deferred;
        \uv_fs_fstat($this->loop, $fh, function($fh, $stat) use ($promisor) {
            if ($fh) {
                $stat["isdir"] = (bool) ($stat["mode"] & \UV::S_IFDIR);
                $stat["isfile"] = !$stat["isdir"];
                $promisor->succeed($stat);
            } else {
                $promisor->succeed();
            }
        });

        return $promisor->promise();
    }

    private function doFsRead($fh, int $offset, int $len): Promise {
        $promisor = new Deferred;
        \uv_fs_read($this->loop, $fh, $offset, $len, function($fh, $nread, $buffer) use ($promisor) {
            $promisor->succeed(($nread < 0) ? false : $buffer);
        });

        return $promisor->promise();
    }

    /**
     * {@inheritdoc}
     */
    public function put(string $path, string $contents): Promise {
        return resolve($this->doPut($path, $contents), $this->reactor);
    }

    private function doPut(string $path, string $contents): \Generator {
        $flags = \UV::O_WRONLY | \UV::O_CREAT;
        $mode = \UV::S_IRWXU | \UV::S_IRUSR;
        $this->reactor->addRef();
        if (!$fh = yield $this->doFsOpen($path, $flags, $mode)) {
            $this->reactor->delRef();
            throw new \RuntimeException(
                "Failed opening write file handle"
            );
        }

        $promisor = new Deferred;
        $len = strlen($contents);
        \uv_fs_write($this->loop, $fh, $contents, $offset = 0, function($fh, $result) use ($promisor, $len) {
            \uv_fs_close($this->loop, $fh, function() use ($promisor, $result, $len) {
                $this->reactor->delRef();
                if ($result < 0) {
                    $promisor->fail(new \RuntimeException(
                        uv_strerror($result)
                    ));
                } else {
                    $promisor->succeed($len);
                }
            });
        });

        return yield $promisor->promise();
    }
}
