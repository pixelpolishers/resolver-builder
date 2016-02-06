<?php

namespace PixelPolishers\ResolverBuilder\Driver;

interface DriverInterface
{
    public function getBranches();
    public function getCommit($reference);
    public function getCommitUrl($commit);
    public function getPackageName();
    public function getResolverInformation($identifier);
    public function getSshUrl();
    public function getTags();
    public function getUrl();
}