<?php

namespace SionModel\Error;

/**
 * Reads the exception-reporting configuration, in one place so the factories
 * cannot drift from each other.
 */
class Config
{
    /**
     * @param array $appConfig the merged application config
     * @return array
     */
    public static function notifications(array $appConfig)
    {
        $sionModel = isset($appConfig['sion_model']) && is_array($appConfig['sion_model'])
            ? $appConfig['sion_model']
            : [];
        $options = isset($sionModel['exception_notifications']) && is_array($sionModel['exception_notifications'])
            ? $sionModel['exception_notifications']
            : [];

        $options += [
            'enabled'             => true,
            'store_path'          => 'data/exceptions',
            'to'                  => [],
            'from'                => null,
            'from_name'           => null,
            'subject_prefix'      => null,
            'ignore_classes'      => [],
            'spike_counts'        => [10, 100, 1000],
            'max_fingerprints'    => 500,
            'max_emails_per_hour' => 20,
            'max_write_up_bytes'  => 262144,
            'ring_size'           => 3,
            'breaker_seconds'     => 900,
            'capture'             => [],
        ];

        $options['capture'] = (array) $options['capture'] + [
            'ip'       => 'truncate',
            'identity' => 'id',
            'params'   => 'keys',
        ];

        return $options;
    }

    /**
     * The application root.
     *
     * public/index.php chdirs to it before anything else runs, which is the same
     * assumption the existing application_log_path and exceptions_log_path
     * settings already make with their relative paths.
     *
     * @param array $appConfig
     * @return string
     */
    public static function appRoot(array $appConfig)
    {
        $sionModel = isset($appConfig['sion_model']) && is_array($appConfig['sion_model'])
            ? $appConfig['sion_model']
            : [];
        if (isset($sionModel['application_root']) && '' !== $sionModel['application_root']) {
            return rtrim(str_replace('\\', '/', $sionModel['application_root']), '/');
        }
        $cwd = getcwd();
        return false === $cwd ? '.' : rtrim(str_replace('\\', '/', $cwd), '/');
    }

    /**
     * Absolute path to the exception store.
     *
     * @param array $appConfig
     * @return string
     */
    public static function storePath(array $appConfig)
    {
        $options = self::notifications($appConfig);
        $path    = str_replace('\\', '/', (string) $options['store_path']);
        if ('/' === substr($path, 0, 1)) {
            return rtrim($path, '/');
        }
        return self::appRoot($appConfig) . '/' . rtrim($path, '/');
    }
}
