<?php

declare(strict_types=1);

namespace J7\PowerFunnel\Domains\VerifyLink\Services;

/**
 * Nonce 服務
 */
final class NonceService {
    
    
    /**
     * 建立 Nonce 服務
     *
     * @param string $key
     * @param int $ttl 時間限制(秒)
     */
    public function __construct(string $key, int $ttl = 600) {
    
    }
    
    /** @return string 創建 nonce */
    public function create(  ):string {
    
    }
    
    /** @return bool 是否通過 */
    public function verify(  ):bool {
    
    }
}