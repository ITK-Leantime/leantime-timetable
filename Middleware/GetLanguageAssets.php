<?php
// phpcs:ignoreFile

namespace Leantime\Plugins\TimeTable\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Leantime\Core\Environment;
use Leantime\Core\IncomingRequest;
use Symfony\Component\HttpFoundation\Response;
use Leantime\Core\Language;

/**
 * https://github.com/Leantime/plugin-template/blob/main/Middleware/GetLanguageAssets.php
 */
class GetLanguageAssets
{
    /**
     * Constructor.
     */
    public function __construct(
        private Language $language,
        private Environment $config,
    ) {
    }

    /**
     * @param \Closure(IncomingRequest): Response $next
     **/
    public function handle(IncomingRequest $request, Closure $next): Response
    {
        $languageArray = Cache::get('timeTable.languageArray', []);

        // @phpstan-ignore-next-line
        if (! empty($languageArray)) {
            $this->language->ini_array = array_merge($this->language->ini_array, $languageArray);
            return $next($request);
        }

        if (! Cache::store('installation')->has('timeTable.language.en-US')) {
            $languageArray += parse_ini_file(__DIR__ . '/../Language/en-US.ini', true);
        }

        // @phpstan-ignore-next-line
        if (($language = $_SESSION['usersettings.language'] ?? $this->config->language) !== 'en-US') {
            if (! Cache::store('installation')->has('timeTable.language.' . $language)) {
                Cache::store('installation')->put(
                    'timeTable.language.' . $language,
                    parse_ini_file(__DIR__ . '/../Language/' . $language . '.ini', true)
                );
            }

            $languageArray = array_merge($languageArray, Cache::store('installation')->get('timeTable.language.' . $language));
        }

        Cache::put('timeTable.languageArray', $languageArray);

        $this->language->ini_array = array_merge($this->language->ini_array, $languageArray);
        return $next($request);
    }
}