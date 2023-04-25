<?php
declare(strict_types=1);

namespace SKien\Google;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class to connect to the google API using OAuth2 authentication.
 *
 * This class only usese cURL.
 *
 * Best practice is to use the OAuth client JSON configuration file,
 * which can be downloaded from Google Cloud Console, to set all project
 * and customer specific information (IDs, secrets, URIs).
 *
 * Create a client configuration at https://console.cloud.google.com
 *
 * @link https://console.cloud.google.com
 *
 * @author Stefanius <s.kientzler@online.de>
 * @copyright MIT License - see the LICENSE file for details
 */
class GClient
{
    /** GET request */
    public const GET = 0;
    /** POST request */
    public const POST = 1;
    /** PUT request */
    public const PUT = 2;
    /** PATCH request */
    public const PATCH = 3;
    /** DELETE request */
    public const DELETE = 4;

    /** Default endpoint for the google OAuth2 authentication   */
    protected const DEF_AUTH_URI = 'https://accounts.google.com/o/oauth2/auth';
    /** Default endpoint to request access/refresh tokens   */
    protected const DEF_TOKEN_URI = 'https://oauth2.googleapis.com/token';

    /** @var string client-ID of the google cloud project     */
    protected string $strProjectID = '';
    /** @var string client-ID within the google cloud project     */
    protected string $strClientID = '';
    /** @var string Endpoint for the google OAuth2 authentication     */
    protected string $strAuthURI = '';
    /** @var string Endpoint to request access/refresh tokens     */
    protected string $strTokenURI = '';
    /** @var string client secret for authentication     */
    protected string $strClientSecret = '';
    /** @var string redirect URI configured for the client     */
    protected string $strRedirectURI = '';
    /** @var array<string>  requested scope     */
    protected array $aScope = [];
    /** @var array<mixed>   received access token     */
    protected array $aAccessToken = [];
    /** @var string   received refresh token     */
    protected string $strRefreshToken = '';

    /** @var int response code of the latest HTTP request     */
    protected int $iLastResponseCode = 0;
    /** @var string error description if the latest HTTP request has failed     */
    protected string $strLastError = '';
    /** @var string status if the latest HTTP request has failed     */
    protected string $strLastStatus = '';

    /** @var LoggerInterface    loger     */
    protected LoggerInterface $oLogger;

    /**
     * @param LoggerInterface $oLogger
     */
    public function __construct(LoggerInterface $oLogger = null)
    {
        // ensure we have a valid logger instance
        $this->oLogger = $oLogger ?? new NullLogger();
        $this->strAuthURI = self::DEF_AUTH_URI;
        $this->strTokenURI = self::DEF_TOKEN_URI;
    }

    /**
     * Set the OAuth2 client configuration from the google API console.
     * The method tries to extract
     * - $strClientID
     * - $strProjectID
     * - $strAuthURI
     * - $strTokenURI
     * - $strClientSecret
     * - $strRedirectURI
     * from the JSON config file.
     * @param string $strClientSecrets    filename
     */
    public function setOAuthClient(string $strClientSecrets) : void
    {
        if (file_exists($strClientSecrets)) {
            $strOAuthClient = file_get_contents($strClientSecrets);
            $aOAuthClient = json_decode($strOAuthClient, true);
            if ($aOAuthClient !== null && (isset($aOAuthClient['web']) || isset($aOAuthClient['installed']))) {
                $aData = $aOAuthClient['web'] ?? $aOAuthClient['installed'];
                $this->strClientID = $aData['client_id'] ?? '';
                $this->strProjectID = $aData['project_id'] ?? '';
                $this->strAuthURI = $aData['auth_uri'] ?? '';
                $this->strTokenURI = $aData['token_uri'] ?? '';
                $this->strClientSecret = $aData['client_secret'] ?? '';
                if (isset($aData['redirect_uris']) and is_array($aData['redirect_uris'])) {
                    $this->strRedirectURI = $aData['redirect_uris'][0];
                }
            } else {
                throw new MissingClientInformationException('No valid client informations from google API console available.');
            }
        } else {
            throw new MissingClientInformationException('Client secrets file [' . $strClientSecrets . '] not found!');
        }
    }

    /**
     * Build the URL to call the google OAuth2.
     * Description for the $strLoginHint param from google docs: <br/>
     * > When your application knows which user it is trying to authenticate, it may provide
     * > this parameter as a hint to the Authentication Server. Passing this hint will either
     * > pre-fill the email box on the sign-in form or select the proper multi-login session,
     * > thereby simplifying the login flow.
     * @param string $strLoginHint  an **existing** google account to preselect in the login form.
     * @throws MissingPropertyException
     * @return string
     */
    public function buildAuthURL(string $strLoginHint = '') : string
    {
        $this->checkProperty($this->strAuthURI, 'auth_uri');
        $this->checkProperty($this->strClientID, 'client_id');
        $this->checkProperty($this->strRedirectURI, 'redirect_uri');
        if (count($this->aScope) < 1) {
            throw new MissingPropertyException('The scope must be specified before call this method!');
        }
        $aLoginParams = [
            'response_type' => 'code',
            'access_type' => 'offline',
            'redirect_uri' => $this->strRedirectURI,
            'client_id' => $this->strClientID,
            'scope' => implode(' ', $this->aScope),
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
        ];
        if (!empty($strLoginHint)) {
            $aLoginParams['login_hint'] = $strLoginHint;
        }
        $this->oLogger->info('GClient: succesfully build auth URL');
        return $this->strAuthURI . '?' . http_build_query($aLoginParams);
    }

    /**
     * Send request to get access and refresh token from a passed auth code.
     * @param string $strAuthCode   the code passed from accounts.google.com
     * @throws MissingPropertyException
     * @return bool
     */
    public function fetchTokens(string $strAuthCode) : bool
    {
        $this->checkProperty($strAuthCode, 'auth code');
        $this->checkProperty($this->strClientID, 'client_id');
        $this->checkProperty($this->strClientSecret, 'client_secret');
        $this->checkProperty($this->strTokenURI, 'token_uri');
        $this->checkProperty($this->strRedirectURI, 'redirect_uri');

        $aData = [
            'grant_type' => 'authorization_code',
            'code' => $strAuthCode,
            'redirect_uri' => $this->strRedirectURI,
            'client_id' => $this->strClientID,
            'client_secret' => $this->strClientSecret,
        ];

        $aHeader = array(
            'Host' => $this->strTokenURI,
            'Cache-Control' => 'no-store',
            'Content-Type' => 'application/x-www-form-urlencoded',
        );

        // since the request only provides an 'expires in' value, we have to keep
        // track of the timestamp, we sent the request
        $timeRequest = time();

        $data = http_build_query($aData);
        if (($strResponse = $this->fetchJsonResponse($this->strTokenURI, self::POST, $aHeader, $data)) !== false) {
            // the body contains the access- and refresh token
            $this->aAccessToken = json_decode($strResponse, true);
            $this->aAccessToken['created'] = $timeRequest;
            $this->strRefreshToken = $this->aAccessToken['refresh_token'] ?? '';
            unset($this->aAccessToken['refresh_token']);
            $this->oLogger->info('GClient: succesfully fetched access token from auth code');
        } else {
            $this->aAccessToken = [];
            $this->strRefreshToken = '';
            $this->oLogger->error(
                'GClient: error fetching access token from auth code', [
                    'responsecode' => $this->iLastResponseCode,
                    'authorization_code' => $strAuthCode,
                ]
            );
        }
        return count($this->aAccessToken) > 0;
    }

    /**
     * Refresh expired access token.
     * @param string $strRefreshToken
     * @throws MissingPropertyException
     * @return array<mixed> new access token
     */
    public function refreshAccessToken(string $strRefreshToken) : array
    {
        $this->checkProperty($strRefreshToken, 'refresh_token');
        $this->checkProperty($this->strClientID, 'client_id');
        $this->checkProperty($this->strClientSecret, 'client_secret');
        $this->checkProperty($this->strTokenURI, 'token_uri');
        $this->checkProperty($this->strRedirectURI, 'redirect_uri');

        $aData = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $strRefreshToken,
            'client_id' => $this->strClientID,
            'client_secret' => $this->strClientSecret,
        ];

        $aHeader = array(
            'Host' => $this->strTokenURI,
            'Cache-Control' => 'no-store',
            'Content-Type' => 'application/x-www-form-urlencoded',
        );

        // since the request only provides an 'expires in' value, we have to keep
        // track of the timestamp, we sent the request
        $timeRequest = time();

        $data = http_build_query($aData);
        if (($strResponse = $this->fetchJsonResponse($this->strTokenURI, self::POST, $aHeader, $data)) !== false) {
            // the body contains the access- and refresh token
            $this->aAccessToken = json_decode($strResponse, true);
            $this->aAccessToken['created'] = $timeRequest;
            $this->oLogger->info('GClient: succesfully refreshed access token');
        } else {
            $this->aAccessToken = [];
            $this->strRefreshToken = '';
            $this->oLogger->error(
                'GClient: error refreshing access token', [
                    'responsecode' => $this->iLastResponseCode,
                    'refresh_token' => $strRefreshToken,
                ]
            );
        }
        return $this->aAccessToken;
    }

    /**
     * The current set access token.
     * @return array<mixed>
     */
    public function getAccessToken() : array
    {
        return $this->aAccessToken;
    }

    /**
     * Set a saved access token.
     * @param string|array<mixed> $token   accesstoken as string (JSON) or array
     */
    public function setAccessToken($token) : void
    {
        if (is_array($token)) {
            $this->aAccessToken = $token;
        } else {
            $aToken = json_decode($token, true);
            if (is_array($aToken)) {
                $this->aAccessToken = $aToken;
            } else {
                $this->aAccessToken = [];
            }
        }
    }

    /**
     * Check, if the actual set access token has expired.
     * It is recommended to set an offset to give time for the execution of
     * the next request.
     * @param int $iOffset additional offset until 'real' expiration
     * @return bool
     */
    public function isAccessTokenExpired(int $iOffset = 20) : bool
    {
        $bExpired = true;
        if (isset($this->aAccessToken['expires_in']) && isset($this->aAccessToken['created'])) {
            $bExpired = time() > $this->aAccessToken['created'] + $this->aAccessToken['expires_in'] + $iOffset;
        }
        return $bExpired;
    }

    /**
     * Response code of the last API request.
     * @return int
     */
    public function getLastResponseCode() : int
    {
        return $this->iLastResponseCode;
    }

    /**
     * Error text if the last API request has failed.
     * @return string
     */
    public function getLastError() : string
    {
        return $this->strLastError;
    }

    /**
     * Status if the last API request has failed.
     * @return string
     */
    public function getLastStatus() : string
    {
        return $this->strLastStatus;
    }

    /**
     * Get the OAuth HTTP header for API requests.
     * @return string
     */
    public function getAuthHeader() : string
    {
        return 'Authorization: ' . $this->aAccessToken['token_type'] . ' ' . $this->aAccessToken['access_token'];
    }

    /**
     * Getter for the current refresh token.
     * @return string
     */
    public function getRefreshToken() : string
    {
        return $this->strRefreshToken;
    }

    /**
     * Getter for the current client ID.
     * @return string
     */
    public function getClientID() : string
    {
        return $this->strClientID;
    }

    /**
     * Getter for the current project ID.
     * @return string
     */
    public function getProjectID() : string
    {
        return $this->strProjectID;
    }

    /**
     * Getter for the current auth URI.
     * @return string
     */
    public function getAuthURI() : string
    {
        return $this->strAuthURI;
    }

    /**
     * Getter for the current token URI.
     * @return string
     */
    public function getTokenURI() : string
    {
        return $this->strTokenURI;
    }

    /**
     * Getter for the current client secret.
     * @return string
     */
    public function getClientSecret() : string
    {
        return $this->strClientSecret;
    }

    /**
     * Getter for the current redirect URI.
     * @return string
     */
    public function getRedirectURI() : string
    {
        return $this->strRedirectURI;
    }

    /**
     * Set the current client ID.
     * @param string $strClientID
     */
    public function setClientID(string $strClientID) : void
    {
        $this->strClientID = $strClientID;
    }

    /**
     * Set the current project ID.
     * @param string $strProjectID
     */
    public function setProjectID(string $strProjectID) : void
    {
        $this->strProjectID = $strProjectID;
    }

    /**
     * Set the current auth URI.
     * @param string $strAuthURI
     */
    public function setAuthURI(string $strAuthURI) : void
    {
        $this->strAuthURI = $strAuthURI;
    }

    /**
     * Set the current token URI.
     * @param string $strTokenURI
     */
    public function setTokenURI(string $strTokenURI) : void
    {
        $this->strTokenURI = $strTokenURI;
    }

    /**
     * Set the current client secret.
     * @param string $strClientSecret
     */
    public function setClientSecret(string $strClientSecret) : void
    {
        $this->strClientSecret = $strClientSecret;
    }

    /**
     * Set the current redirect URI.
     * @param string $strRedirectURI
     */
    public function setRedirectURI(string $strRedirectURI) : void
    {
        $this->strRedirectURI = $strRedirectURI;
    }

    /**
     * Add scope that is neede for following API requests.
     * @param string|array<string> $scope
     */
    public function addScope($scope) : void
    {
        if (is_array($scope)) {
            $this->aScope = array_merge($this->aScope, $scope);
        } else if (!in_array($scope, $this->aScope)) {
            $this->aScope[] = $scope;
        }
    }

    /**
     * Set a logger instance.
     * @param \Psr\Log\LoggerInterface $oLogger
     */
    public function setLogger(LoggerInterface $oLogger) : void
    {
        $this->oLogger = $oLogger;
    }

    /**
     * Cleint request to the API.
     * @param string $strURI
     * @param int $iMethod
     * @param array<string> $aHeader
     * @param string $data
     * @return string|false
     */
    public function fetchJsonResponse(string $strURI, int $iMethod, array $aHeader = [], string $data = '')
    {
        $result = false;

        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $strURI);
        switch ($iMethod) {
            case self::POST:
                curl_setopt($curl, CURLOPT_POST, true);
                break;
            case self::PUT:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
            case self::PATCH:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
                break;
            case self::DELETE:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'PHP cURL Http Request');
        curl_setopt($curl, CURLOPT_HTTPHEADER, $aHeader);
        curl_setopt($curl, CURLOPT_HEADER, true);

        $strResponse = curl_exec($curl);

        $this->iLastResponseCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $iHeaderSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);

        curl_close($curl);

        if ($this->iLastResponseCode == 200) {
            $result = '';
            $this->strLastError = '';
            $this->strLastStatus = '';
            if (is_string($strResponse)) {
                $result = substr($strResponse, $iHeaderSize);
            }
        } else {
            $strError = substr($strResponse, $iHeaderSize);
            if (strlen($strError) > 0)  {
                $aError = json_decode($strError, true);
                if (isset($aError['error'])) {
                    $this->strLastError = $aError['error']['message'] ?? '';
                    $this->strLastStatus = $aError['error']['status'] ?? '';
                    $this->oLogger->error('GClient: ' . $this->strLastError);
                }
            }
        }
        return $result;
    }

    /**
     * Set information about the last error occured.
     * If any error can be detected before an API request is made, use this
     * method to set an reproduceable errormessage.
     * @param int $iResponseCode
     * @param string $strError
     * @param string $strStatus
     */
    public function setError(int $iResponseCode, string $strError, string $strStatus) : void
    {
        $this->iLastResponseCode = $iResponseCode;
        $this->strLastError = $strError;
        $this->strLastStatus = $strStatus;
        $this->oLogger->error('GClient: ' . $this->strLastError);
    }

    /**
     * Parse the header of an HTTP response.
     * @param string $strHeader
     * @return array<string,string>
     */
    public function parseHttpHeader(string $strHeader) : array
    {
        $aHeader = [];
        $strHeader = trim($strHeader);
        $aLine = explode("\n",$strHeader);
        $aHeader['status'] = $aLine[0];
        array_shift($aLine);

        foreach($aLine as $strLine){
            // only consider the first colon, since other colons can also appear in
            // the header value - the rest of such a value would be lost
            // (eg "Location: https: // www ...." - "// www ...." would be gone !)
            $aValue = explode(":", $strLine, 2);

            // header names are NOT case sensitive, so make all lowercase!
            $strName = strtolower(trim($aValue[0]));
            if (count($aValue) == 2) {
                $aHeader[$strName] = trim($aValue[1]);
            } else {
                $aHeader[$strName] = true;
            }
        }
        return $aHeader;
    }

    /**
     * Check, if given property is set and throw exception, if not so.
     * @param string $strValue
     * @param string $strName
     * @throws MissingPropertyException
     */
    private function checkProperty(string $strValue, string $strName) : void
    {
        if (empty($strValue)) {
            throw new MissingPropertyException($strName . ' must be set before call this method!');
        }
    }
}