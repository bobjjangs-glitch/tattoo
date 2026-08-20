<?php
/**
 * .env 파일을 읽어서 $_ENV / getenv()로 사용할 수 있게 만드는 최소 로더.
 * 외부 라이브러리(vlucas/phpdotenv) 없이 순수 PHP로 동작한다.
 */
function loadEnv(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // "값" 또는 '값' 형태로 감싸져 있으면 인용부호 제거
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

/**
 * 환경변수를 읽는 헬퍼. 값이 없으면 $default 반환.
 */
function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
}

// includes/env.php 기준으로 프로젝트 루트의 .env를 자동 로드
loadEnv(__DIR__ . '/../.env');
