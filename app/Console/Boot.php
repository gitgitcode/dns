<?php
namespace App\Console;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Data\CommandHistory;

class Boot extends Command{

    protected $isDebug = false;

    public function __construct()
    {
        parent::__construct();
    }

    protected function arguments($key=null, $default=null)
    {
        return $this->argument($key)?: $default;
    }

    protected function options()
    {
        foreach (func_get_args() as $arg)
            $arr[$arg] = $this->option($arg);
        return $arr;
    }

    protected function infos()
    {
        return $this->info(implode('   ', func_get_args()));
    }

    protected function sleepOnce()
    {
        if(date('i')%5 == 0)
            sleep(5);
    }

    protected function argumentRun()
    {
        $this->start();

        $argument = $this->arguments('argument', '');

        $fun = studly_case($argument);
        if(method_exists($this, $fun)){
            $this->$fun();
        }else{
            $this->error('未找到处理方法');
        }

        $this->end();
    }

    protected function start()
    {
        $this->startTime = Carbon::now();
        $this->info('-------------< Start >-------------');
    }

    protected function end()
    {
        $this->info('-------------< End >-------------');

        $runtime = Carbon::now()->diffInSeconds($this->startTime);

        $signature = substr($this->signature,0,stripos($this->signature,' '))?:$this->signature;

        $parame = [
            'argument' => array_except($this->argument(),['command']),
            'option' => array_except($this->option(),['help','verbose','version','ansi','no-ansi','no-interaction','env']),
        ];

        $this->table(
            // ['开始时间','结束时间','用时','内存峰值'],
            ['命令','开始时间','结束时间','用时'],
            [
                [
                    $signature,
                    $this->startTime,
                    Carbon::now(),
                    gmstrftime('%H时%M分%S秒',$runtime),
                    // sprintf("%.2f", memory_get_peak_usage()/1024/1024) . 'MB'
                ]
            ]);
        CommandHistory::saveData([
            'signature'     => $signature,
            'parame'        => $parame,
            'description'   => $this->description,
            'starttime'     => $this->startTime,
            'endtime'       => Carbon::now(),
            'runtimestring' => gmstrftime('%H时%M分%S秒',$runtime),
            'runtime'       => $runtime
            ]);
    }


    protected function scryed($total, $workers = 8, Array $args)
    {
        $quiet = $this->option('quiet');

        $pids = [];
        for($i = 0; $i < $workers; $i++){
            $pids[$i] = pcntl_fork();
            switch ($pids[$i]) {
                case -1:
                    echo "fork error : {$i} \r\n";
                    exit;
                case 0:
                    $limit = floor($total / $workers);
                    $offset = $i * $limit;

                    if($i == $workers-1) $limit = $total - $offset;

                    $this->info(">>> 一个子进程已开启  |   剩 " . ($workers-$i-1) ." 个  |  pid = " . getmypid() . " | --limit = $limit  |  --offset = $offset");
                    sleep(2); // 这个sleep仅为看清上面的info，可删

                    array_push($args,"--limit=$limit","--offset=$offset");
                    $quiet && array_push($args,'--quiet');

                    pcntl_exec('/usr/bin/php',$args,[]);
                    // exec("/usr/bin/php5 artisan crawler:model autohome --sych-model=false --offset={$offset} --limit={$limit}");
                    // pcntl_exec 与 exec 同样可以执行命令。区别是exec 不会主动输出到shell控制台中
                    exit;
                default:
                    break;
            }
        }

        foreach ($pids as $pid)
            $pid && pcntl_waitpid($pid, $status);
    }

}