<?php


namespace App\Support\Clients;

use App\Http\H;
use GuzzleHttp\Client;
use App\Traits\MakesHttpRequests;

class IPFSClient
{
    use MakesHttpRequests;

    /**
     * The API rest endpoint url for IPFS.
     * @var string
     */
    private string $baseUrl;

    /**
     * The api port
     * @var int
     */
    private int $port;

    /**
     * Number of seconds describing the total timeout of the request in seconds.
     * Use 0 to wait indefinitely (the default behavior).
     *
     * @var int
     */
    private int $timeout;

    /**
     * The current version of the API to use.
     */
    private string $version = 'v0';

    /**
     * The Guzzle HTTP Client instance.
     */
    public Client $client;

    /**
     * IPFSClient constructor.
     *
     * @param string $baseUrl
     * @param int $port
     * @param int $timeout
     * @param bool $debug
     */
    public function __construct(string $baseUrl, int $port, int $timeout = 0)
    {
        $this->baseUrl = $baseUrl;
        $this->port = $port;
        $this->timeout = $timeout;

        $options = [
            'base_uri' => self::format_url("$this->baseUrl:$this->port/api/$this->version/"),
            'timeout' => $this->timeout,
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'prepare_ip' => true,
        ];
        H::prepareOptions($options, $baseUrl);
        $this->client = new Client($options);
    }

    private static function format_url(string $baseUrl)
    {
        return rtrim($baseUrl, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Show IPFS node id info.
     *
     * https://docs.ipfs.tech/reference/kubo/rpc/#api-v0-id
     * @param array $queryParams
     * @return array
     * @throws \App\Exceptions\IPFSException
     */
    public function id(array $queryParams = [])
    {
        return $this->post('id', $queryParams);
    }

    /**
     * Show IPFS object data.
     *
     * https://docs.ipfs.io/reference/http/api/#api-v0-add
     * @param string $hash The path to the IPFS object(s) to be outputted.
     * @param array $queryParams
     * @return mixed
     * @throws \App\Exceptions\IPFSException
     */
    public function cat(string $hash, array $queryParams = [])
    {
        return $this->post("cat/$hash", $queryParams);
    }

    /**
     * Add a file or directory to IPFS.
     *
     * https://docs.ipfs.io/reference/http/api/#api-v0-add
     * @param string|mixed $data a string to send the contents of the file as a string or an fopen resource to stream the contents from a PHP stream.
     * @param string $fileName The name of the file
     * @param array $queryParams
     * @return mixed
     * @throws \App\Exceptions\IPFSException
     */
    public function add($data, $fileName = '', array $queryParams = [])
    {
        $multipart = [
            'multipart' => [
                [
                    'name'     => "data",
                    'contents' => $data,
                    'filename' => $fileName,
                ],
            ],
        ];

        return $this->post("add", $queryParams, $multipart);
    }

    /**
     * List directory contents for UnixFS objects.
     *
     * https://docs.ipfs.io/reference/http/api/#api-v0-ls
     * @param string $hash The path to the IPFS object(s) to list links from.
     * @param array $queryParams
     * @return mixed
     * @throws \App\Exceptions\IPFSException
     */
    public function ls(string $hash, array $queryParams = [])
    {
        return $this->post("ls/$hash", $queryParams);
    }

    /**
     * Get the size of the UnixFS object.
     *
     * https://docs.ipfs.io/reference/http/api/#api-v0-object-stat
     * @param string $hash The path to the IPFS object to get the size from.
     * @param array $queryParams
     * @return mixed
     * @throws \App\Exceptions\IPFSException
     */
    public function size(string $hash, array $queryParams = [])
    {
        return $this->post("object/stat/$hash", $queryParams)['CumulativeSize'];
    }

    /**
     * Get stats for the DAG node named by <key>.
     *
     * https://docs.ipfs.io/reference/http/api/#api-v0-object-stat
     * @param string $hash The path to the IPFS object to get the stats from.
     * @param array $queryParams
     * @return mixed
     * @throws \App\Exceptions\IPFSException
     */
    public function stats(string $hash, array $queryParams = [])
    {
        return $this->post("object/stat/$hash", $queryParams);
    }

    /**
     * Pin objects to local storage.
     *
     * https://docs.ipfs.io/reference/http/api/#api-v0-pin-add
     * @param string $hash The path to the IPFS object that you want to pin.
     * @param array $queryParams
     * @return mixed
     * @throws \App\Exceptions\IPFSException
     */
    public function pin(string $hash, array $queryParams = [])
    {
        return $this->post("pin/add/$hash", $queryParams);
    }

    /**
     * Remove pinned objects from local storage.
     *
     * https://docs.ipfs.io/reference/http/api/#api-v0-pin-rm
     * @param string $hash The path to the IPFS object that you want to unpin.
     * @param array $queryParams
     * @return mixed
     * @throws \App\Exceptions\IPFSException
     */
    public function unpin(string $hash, array $queryParams = [])
    {
        return $this->post("pin/rm/$hash", $queryParams);
    }

    public function pin_verify(string $hash, array $queryParams = [])
    {
        return $this->post("pin/verify", $queryParams);
    }
    public function getPinned(array $queryParams = [])
    {
        return $this->post("pin/ls", $queryParams);
    }
}
