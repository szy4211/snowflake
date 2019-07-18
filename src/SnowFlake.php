<?php
/**
 * @description: 雪花算法
 *
 * @date 2019-07-17
 * @author zornshuai@foxmail.com
 */

namespace SnowFlake;

/**
 * Class SnowFlake
 * @description: 雪花算法
 *
 * 1位标识，空缺
 * 41位时间截(毫秒级), 当前时间戳 - 初始时间戳
 * 10位的数据机器位，可以部署在1024个节点
 * 12位序列，毫秒内的计数，12位的计数顺序号支持每个节点每毫秒(同一机器，同一时间截)产生4096个ID序号
 * 加起来刚好64位，为一个64位2进制数
 *
 * @package App\Services
 * @date 2019-07-17
 */
class SnowFlake
{
    const EPOCH = 1533700800000; // 自定义开始（毫秒）基值，当小于当前时间（毫秒）
    const LEFT_TIME_MILLIS = 22; // 时间戳左移 12 + 10
    const LEFT_MACHINE = 12; // 机器号左移 12
    const MAX_MACHINE_ID = 1023; // 最大机器号 2^10 - 1
    const MAX_SEQUENCE = 4095; // 最大序列号 2^12 - 1

    protected static $machineId; // 机器号

    /**
     * @var \Redis 此处使用Redis来确保并发的线程安全
     */
    protected static $redisService;


    /**
     * SnowFlake constructor.
     * @param $redisService
     * @param int $machineId
     * @throws \Exception
     */
    public function __construct($redisService, int $machineId)
    {
        if ($machineId < 0 || $machineId > self::MAX_MACHINE_ID) {
            throw new \Exception('The $machineId must be between 0 and ' . self::MAX_MACHINE_ID . '. ');
        }

        self::$machineId    = $machineId;
        self::$redisService = $redisService;
    }

    /**
     * @description: 生成ID
     *
     * @return int
     * @date 2019-07-18
     */
    public function generate(): int
    {
        $currTimeMillis = $this->getTimeMillis();
        $sequence       = $this->getSequence($currTimeMillis);
        $sequence       = $sequence & self::MAX_SEQUENCE;

        if (0 === $sequence) {
            //  获取下一个毫秒时间段
            $currTimeMillis = $this->getNextTimeMillis($currTimeMillis);
        }

        return ($currTimeMillis - self::EPOCH) << self::LEFT_TIME_MILLIS
            | self::$machineId << self::LEFT_MACHINE
            | $sequence;
    }

    /**
     * @description: 获取下一毫秒时间戳
     *
     * @param $currTimeMillis
     * @return int
     * @date 2019-07-18
     */
    protected function getNextTimeMillis(int $currTimeMillis): int
    {
        do {
            $next = $this->getTimeMillis();
        } while ($next <= $currTimeMillis);

        return $next;
    }

    /**
     * @description: 获取自增值
     * 使用缓存来确保同一毫秒内多线程读取到唯一数值
     *
     * @param int $time
     * @return bool|int
     * @date 2019-07-18
     */
    protected function getSequence(int $time): int
    {
        $cacheKey = 'snowflake:' . self::$machineId . '-' . $time;
        $sequence = self::$redisService->incr($cacheKey);
        self::$redisService->expire($cacheKey, 1);

        return $sequence;
    }

    /**
     * @description: 获取毫秒时间戳
     *
     * @return int
     * @date 2019-07-18
     */
    protected function getTimeMillis(): int
    {
        return floor(microtime(true) * 1000);
    }
}
