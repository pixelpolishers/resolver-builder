<?php

namespace PixelPolishers\ResolverBuilder\Driver;

interface DriverInterface
{
    public function getBranches();
    public function getCommit($reference);
    public function getCommitUrl($commit);
    public function getDistInformation($reference);
    public function getPackageName();
    public function getResolverInformation($reference);
    public function getSourceInformation($reference);
    public function getSshUrl();
    public function getTags();
    public function getUrl();
}