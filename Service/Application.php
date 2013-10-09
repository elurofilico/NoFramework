<?php
/**
 * NoFramework
 *
 * @author Roman Zaykin <roman@noframework.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @link http://noframework.com
 */

namespace NoFramework\Service;

class Application
{
    use \NoFramework\MagicProperties;

    protected $pidfile;
    protected $timeout = 3600;
    protected $action;

    protected function __property_run()
    {
        $run = $_SERVER['argv'];
        array_shift($run);
        return $run;
    }

    protected function main()
    {
        foreach ($this->run as $action) {
            try {
                $this->action->$action->run();
            } catch (Exception\Stop $e) {
            } catch (\Exception $e) {
                $this->action->$action->error_log->write($e);
            } 
        }
    }

    public function start($main = false)
    {
        if ($this->pidfile) {
            $pidfile = $this->pidfile;

            if (is_string($pidfile)) {
                $pidfile = new PidFile;
                $pidfile->path = $this->pidfile;
            }

            if (!$pidfile->check($this->timeout)) {
                $pidfile->write();
                $result = $this->main();
                $pidfile->delete();
                return $result;
            }
        } else {
            return $this->main();
        }
    }
}

