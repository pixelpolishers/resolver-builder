<?php

namespace PixelPolishers\ResolverBuilder\Driver;

use RuntimeException;

class Github implements DriverInterface
{
    private $url;
    private $owner;
    private $repository;
    private $originUrl;
    private $private;
    private $rootIdentifier;
    private $authToken;

    public function __construct($url, $authToken = null)
    {
        $this->url = $url;
        $this->authToken = $authToken;

        $this->initialize();
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getCommitUrl($commit)
    {
        return sprintf('https://github.com/%s/%s/commit/%s', $this->owner, $this->repository, $commit);
    }

    public function getSshUrl()
    {
        return sprintf('git@%s:%s/%s.git', $this->originUrl, $this->owner, $this->repository);
    }

    public function getPackageName()
    {
        return $this->owner . '/' . $this->repository;
    }

    private function initialize()
    {
        preg_match('#^(?:(?:https?|git)://([^/]+)/|git@([^:]+):)([^/]+)/(.+?)(?:\.git|/)?$#', $this->url, $match);

        $this->owner = $match[3];
        $this->repository = $match[4];
        $this->originUrl = 'github.com';

        $this->fetchRootIdentifier();
    }

    private function fetchRootIdentifier()
    {
        $url = sprintf('https://api.github.com/repos/%s/%s', $this->owner, $this->repository);
        $content = $this->getContent($url);

        $this->owner = $content['owner']['login'];
        $this->repository = $content['name'];
        $this->private = $content['private'];

        if (isset($content['default_branch'])) {
            $this->rootIdentifier = $content['default_branch'];
        } elseif (isset($repoData['master_branch'])) {
            $this->rootIdentifier = $content['master_branch'];
        } else {
            $this->rootIdentifier = 'master';
        }
    }

    public function getDistInformation($reference)
    {
        $url = sprintf('https://api.github.com/repos/%s/%s/zipball/%s', $this->owner, $this->repository, $reference);

        return ['type' => 'zip', 'url' => $url, 'reference' => $reference];
    }

    public function getSourceInformation($reference)
    {
        if ($this->private) {
            $url = $this->getSshUrl();
        } else {
            $url = $this->getUrl();
        }

        return ['type' => 'git', 'url' => $url, 'reference' => $reference];
    }

    public function getCommit($reference)
    {
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/git/commits/%s',
            $this->owner,
            $this->repository,
            $reference
        );

        return $this->getContent($url);
    }

    public function getBranches()
    {
        $url = sprintf('https://api.github.com/repos/%s/%s/branches', $this->owner, $this->repository);
        $json = $this->getContent($url);

        return $json ?: [];
    }

    public function getTags()
    {
        $url = sprintf('https://api.github.com/repos/%s/%s/tags', $this->owner, $this->repository);
        $json = $this->getContent($url);

        return $json ?: [];
    }

    public function getResolverInformation($reference)
    {
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/contents/resolver.json?ref=%s',
            $this->owner,
            $this->repository,
            urlencode($reference)
        );

        $json = $this->getContent($url);

        if ($json['encoding'] !== 'base64') {
            print_r($json);
            exit;
            throw new RuntimeException(sprintf('Encoding "%s" is not supported for "%s"', $json['encoding'], $url));
        }

        $fileContent = base64_decode($json['content']);
        $fileJson = json_decode($fileContent);

        return $fileJson;
    }

    private function getContent($url, $raw = false)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'pixelpolishers/resolver-builder');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/vnd.github.v3+json',
            'Authorization: token ' . $this->authToken,
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        $json = json_decode($content, true);

        if ($httpCode !== 200) {
            throw new RuntimeException($json['message'], $httpCode);
        }

        return $raw ? $content : $json;
    }
}