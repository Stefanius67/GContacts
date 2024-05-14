<?php
declare(strict_types=1);

namespace SKien\Google;

/**
 * Helper class for the maintenance of the oauth client data and tokens.
 *
 * > **Note:** <br/>
 * > This class ofers the functionality to save tokens in files on the server.
 * > This 'mode' was implemented for development purposes:
 * > - for easier read the tokens returned by the google API
 * > - to work on a local development environment where the development server
 * >   is NOT the localhost but the redirectURI  also is not accessible from
 * >   outside. In that case, only the login have to be made directly on the
 * >   machine where the webserver runs - all subsequent calls to the API uses
 * >   the tokens from the files created after the oauth-login has called the
 * >   redirectURI.
 * >
 * > **For  security issues DO NOT USE THIS MODE for public environments!!!**
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class GSecrets
{
    /** refreshtoken are saved in cookies   */
    public const TOKEN_COOKIE = 0;
    /** refreshtoken are saved in files on the server   */
    public const TOKEN_FILE = 1;

    /** Filename to save access token     */
    protected const ACCESS_TOKEN = 'google_access_token';
    /** Filename to save refresh token     */
    protected const REFRESH_TOKEN = 'google_refresh_token';

    /** @var int where to save the access- and refreshtoken     */
    protected int $iSaveTokensAt = self::TOKEN_COOKIE;
    /** @var int time, how long the login is valid (-> how long is the refreshToken saved)    */
    protected int $iKeepLoggedIn = 0;
    /** @var string the path, where the secrets can be found     */
    protected string $strSecretsPath = './secrets/';
    /** @var string the path, where the secrets can be found     */
    protected string $strSecretsFilename = 'google_secrets.json';

    /**
     * Create an instance of the class.
     * **DO NOT USE `$iSaveTokensAt = self::TOKEN_FILE` IN PUBLIC ENVIRONMENT** <br/>
     * In 'self::TOKEN_FILE - Mode' the `$iKeepLoggedIn` param is ignored since the tokens
     * saved in files that never expires unless they are deleted manually...
     * @param int $iKeepLoggedIn    0 (default) -> session only, -1 -> 'forever', other value -> days to keep the login
     * @param int $iSaveTokensAt    `self::TOKEN_COOKIE` (default) or `self::TOKEN_FILE`
     */
    public function __construct(int $iKeepLoggedIn = 0, int $iSaveTokensAt = self::TOKEN_COOKIE)
    {
        $this->iSaveTokensAt = $iSaveTokensAt;
        $this->iKeepLoggedIn = ($iKeepLoggedIn * 86400);
        if ($iKeepLoggedIn < 0) {
            // -1 means nearly 'forever' ... with the following value login expires in about 20 years
            $this->iKeepLoggedIn = 630720000;
        }
    }

    /**
     * Set the path where the secrets- and token files are located.
     * The directory must not be write-protected and should be protected
     * agains access from outside (.htacces: `deny from all`).
     * @param string $strSecretsPath
     */
    public function setSecretsPath(string $strSecretsPath) : void
    {
        $this->strSecretsPath = rtrim($strSecretsPath, '/') . '/';
    }

    /**
     * Sets the filename of the google oauth client configuration file.
     * @param string $strSecretsFilename
     */
    public function setSecretsFilename(string $strSecretsFilename) : void
    {
        $this->strSecretsFilename = $strSecretsFilename;
    }

    /**
     * Get full path and filename of the oauth client configuration file.
     * @return string
     */
    public function getClientSecrets() : string
    {
        return $this->strSecretsPath . $this->strSecretsFilename;
    }

    /**
     * Gets the last saved accesToken.
     * @return array<mixed>
     */
    public function getAccessToken() : array
    {
        $aToken = [];
        if (isset($_COOKIE[self::ACCESS_TOKEN])) {
            $aToken = json_decode($_COOKIE[self::ACCESS_TOKEN], true);
            if ($aToken === false) {
                $aToken = [];
            }
        }
        return $aToken;
    }

    /**
     * Save accessToken.
     * @param array<mixed> $aToken
     */
    public function saveAccessToken(array $aToken) : void
    {
        $strToken = json_encode($aToken);
        if ($strToken !== false) {
            // the access token has a limited validity anyway, we set the lifetime of this
            // cookie to the session
            $this->setCookie(self::ACCESS_TOKEN, $strToken);
            if ($this->iSaveTokensAt == self::TOKEN_FILE) {
                // since self::TOKEN_FILE only should be used in debug/local environment
                // we just save the accesToken to file to get easier access to its value...
                file_put_contents($this->strSecretsPath . self::ACCESS_TOKEN . '.json', $strToken);
            }
        }
    }

    /**
     * Gets the last saved refreshToken.
     * @return string
     */
    public function getRefreshToken() : string
    {
        $strRefreshToken = '';
        if ($this->iSaveTokensAt == self::TOKEN_COOKIE) {
            $strRefreshToken = $_COOKIE[self::REFRESH_TOKEN] ?? '';
        } else if (file_exists($this->strSecretsPath . self::REFRESH_TOKEN . '.txt')) {
            $strRefreshToken = file_get_contents($this->strSecretsPath . self::REFRESH_TOKEN . '.txt');
        }
        return $strRefreshToken;
    }

    /**
     * Save the refreshToken.
     * @param string $strRefreshToken
     */
    public function saveRefreshToken(string $strRefreshToken) : void
    {
        if ($this->iSaveTokensAt == self::TOKEN_COOKIE) {
            $iExpires = ($this->iKeepLoggedIn > 0) ?  time() + $this->iKeepLoggedIn : 0;
            $this->setCookie(self::REFRESH_TOKEN, $strRefreshToken, $iExpires);
        } else {
            file_put_contents($this->strSecretsPath . self::REFRESH_TOKEN . '.txt', $strRefreshToken);
        }
    }

    /**
     * Logout from the google account.
     * Deletes a saved access- and refreshToken.
     */
    public function logout() : void
    {
        $this->setCookie(self::ACCESS_TOKEN, '');
        if ($this->iSaveTokensAt == self::TOKEN_COOKIE) {
            $this->setCookie(self::REFRESH_TOKEN, '');
        } else {
            /** @scrutinizer ignore-unhandled */
            @unlink($this->strSecretsPath . self::REFRESH_TOKEN . '.txt');
            /** @scrutinizer ignore-unhandled */
            @unlink($this->strSecretsPath . self::ACCESS_TOKEN . '.json');
        }
    }

    /**
     * Sets the specified cookie.
     * @param string $strCookie
     * @param string $strValue
     * @param int $iExpires
     */
    private function setCookie(string $strCookie, string $strValue, int $iExpires = 0) : void
    {
        setcookie($strCookie, $strValue, [
            'expires' => $iExpires,
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
    }
}