<?php

namespace Yansongda\Pay\Gateways\Wechat;

use Yansongda\Pay\Exceptions\GatewayException;
use Yansongda\Pay\Exceptions\InvalidArgumentException;
use Yansongda\Pay\Exceptions\InvalidConfigException;
use Yansongda\Pay\Exceptions\InvalidSignException;
use Yansongda\Pay\Log;
use Yansongda\Supports\Collection;
use Yansongda\Supports\Traits\HasHttpRequest;

class Support
{
    use HasHttpRequest;

    /**
     * Instance.
     *
     * @var Support
     */
    private static $instance;

    /**
     * Wechat gateway.
     *
     * @var string
     */
    protected $baseUri = 'https://api.mch.weixin.qq.com/';

    /**
     * Get instance.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @return Support
     */
    public static function getInstance()
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Request wechat api.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param string $endpoint
     * @param array $data
     * @param string $certClient
     * @param string $certKey
     *
     * @return Collection
     */
    public static function requestApi($endpoint, $data, $certClient = null, $certKey = null): Collection
    {
        Log::debug('Request To Wechat Api', [self::baseUri() . $endpoint, $data]);

        $result = self::getInstance()->post(
            $endpoint,
            self::toXml($data),
            ($certClient && $certKey) ? ['cert' => $certClient, 'ssl_key' => $certKey] : null
        );

        $result = self::fromXml($result);

        if (self::generateSign($result) !== $result['sign']) {
            Log::warning('Wechat Sign Verify FAILED', $result);

            throw new InvalidSignException('Wechat Sign Verify FAILED', 3, $result);
        }

        if (isset($result['return_code']) && $result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
            return new Collection($result);
        }

        throw new GatewayException(
            'Get Wechat API Error:' . $result['return_msg'] . $result['err_code_des'] ?? '',
            20000,
            $result
        );
    }

    /**
     * Generate wechat sign.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param array $data
     *
     * @return string
     */
    public static function generateSign($data, $key = null): string
    {
        if (is_null($key)) {
            throw new InvalidArgumentException('Missing Wechat Config -- [key]');
        }

        ksort($data);

        $string = md5(self::GenerateSignContent($data).'&key='.$key);

        return strtoupper($string);
    }

    /**
     * Generate sign content.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param array $data
     *
     * @return string
     */
    public static function generateSignContent($data): string
    {
        $buff = '';

        foreach ($data as $k => $v) {
            $buff .= ($k != 'sign' && $v != '' && !is_array($v)) ? $k.'='.$v.'&' : '';
        }

        return trim($buff, '&');
    }

    /**
     * Convert array to xml.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param array $data
     *
     * @return string
     */
    public static function toXml($data): string
    {
        if (!is_array($data) || count($data) <= 0) {
            throw new InvalidArgumentException('Convert To Xml Error! Invalid Array!');
        }

        $xml = '<xml>';
        foreach ($data as $key => $val) {
            $xml .= is_numeric($val) ? '<'.$key.'>'.$val.'</'.$key.'>' :
                                       '<'.$key.'><![CDATA['.$val.']]></'.$key.'>';
        }
        $xml .= '</xml>';

        return $xml;
    }

    /**
     * Convert xml to array.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @param string $xml
     *
     * @return array
     */
    public static function fromXml($xml): array
    {
        if (!$xml) {
            throw new InvalidArgumentException('Convert To Array Error! Invalid Xml!');
        }

        libxml_disable_entity_loader(true);

        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA), JSON_UNESCAPED_UNICODE), true);
    }

    /**
     * Wechat gateway.
     *
     * @author yansongda <me@yansongda.cn>
     *
     * @return string
     */
    public static function baseUri(): string
    {
        return self::getInstance()->baseUri;
    }
}
