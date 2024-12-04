<?php
//projects/                                                               > projects
//projects/paper/                                                         > versions
//projects/paper/versions/<version>/                                      > builds
//projects/paper/versions/<version>/builds/<build>                        > builds info + filename
//projects/paper/versions/<version>/builds/<build>/downloads/<filename>   > file

class papermc_api_v2{
    public static function getLatest(string $projectName):string{
        mklog('general','Downloading the latest version of papermc');
        $latestVersion = self::getLatestVersion($projectName);
        $latestBuild = self::getLatestBuild($projectName,$latestVersion);
        return $latestVersion . "-" . $latestBuild;
    }
    public static function listBuilds(string $projectName, string $version, string $channel = "default"):array{
        $buildsInfo = json::readFile("https://api.papermc.io/v2/projects/" . $projectName . "/versions" . "/" . $version . "/builds");
        $defaultBuilds = array();
        foreach($buildsInfo['builds'] as $build){
            if($build['channel'] === $channel){
                $defaultBuilds[] = $build['build'];
            }
        }
        return $defaultBuilds;
    }
    public static function getLatestBuild(string $projectName, string $version):string|int{
        return max(self::listBuilds($projectName,$version));
    }
    public static function listVersions(string $projectName):array{
        return json::readFile("https://api.papermc.io/v2/projects/" . $projectName)['versions'];
    }
    public static function getLatestVersion(string $projectName):bool|string{
        $paperInfo = self::listVersions($projectName);
        $versions = array();
        foreach($paperInfo as $version){
            $v1 = substr($version,0,strpos($version,"."));
            $v2 = substr($version,strpos($version,".")+1);
            if(strripos($v2,".") === false){
                $v3 = "";
            }
            else{
                $v3 = substr($v2,strripos($v2,".")+1);
                $v2 = substr($v2,0,strpos($v2,"."));
            }

            $v1 = str_pad($v1,3,"0",STR_PAD_LEFT);
            $v2 = str_pad($v2,3,"0",STR_PAD_LEFT);
            $v3 = str_pad($v3,3,"0",STR_PAD_LEFT);

            $vnum = $v1 . $v2 . $v3;

            if(preg_match("/^[0-9]+$/", $vnum) === 1){
                $versions[$version] = $vnum;
            }
        }
        $latestVersion = max($versions);
        foreach($versions as $versionName => $versionValue){
            if($versionValue == $latestVersion){
                return $versionName;
            }
        }
        return false;
    }
    //public static function command($line):void{}
    public static function filePath(string $projectName, string $version, int|string $build, bool $autoDownload = true):bool|string{
        if(is_string($build)){
            $build = intval($build);
        }
        $filePath = settings::read('libraryDir') . '\projects\\' . $projectName . '\versions\\' . $version . '\builds\\' . $build . '.json';
        retry:
        if(is_file($filePath)){
            return substr($filePath,0,-5) . '\downloads\\' . json::readFile($filePath)['downloads']['application']['name'];
        }
        else{
            if($autoDownload){
                if(self::downloadJar($projectName, $version, $build)){
                    goto retry;
                }
            }
        }
        return false;
    }
    public static function downloadJar(string $projectName, string $version, int|string $build):bool{

        if(is_string($build)){
            $build = intval($build);
        }

        $libraryDir = settings::read("libraryDir");
        $apiUrl = settings::read("apiUrl");
        $path = "/projects";
        $projectsPath = $path;
        $onlineProjectInfo = json::readFile($apiUrl . $path,false);
        $localProjectInfo = json::readFile($libraryDir . $path . ".json",true,array("projects"=>array()));

        if(in_array($projectName,$onlineProjectInfo['projects'])){
            if(!in_array($projectName,$localProjectInfo['projects'])){
                array_push($localProjectInfo['projects'],$projectName);
            }
            $path .= "/" . $projectName;
            $versionsPath = $path;
            $onlineVersionInfo = json::readFile($apiUrl . $path,false);
            $localVersionInfo = json::readFile($libraryDir . $path . ".json",true,array("versions"=>array()));
            if(in_array($version,$onlineVersionInfo['versions'])){
                if(!in_array($version,$localVersionInfo['versions'])){
                    array_push($localVersionInfo['versions'],$version);
                }
                $path .= "/versions" . "/" . $version;
                $buildsPath = $path;
                $onlineBuildsInfo = json::readFile($apiUrl . $path,false);
                $localBuildsInfo = json::readFile($libraryDir . $path . ".json",true,array("builds"=>array()));
                if(in_array($build,$onlineBuildsInfo['builds'])){
                    if(!in_array($build,$localBuildsInfo['builds'])){
                        array_push($localBuildsInfo['builds'],$build);
                    }
                    $path .= "/builds" . "/" . $build;
                    $buildPath = $path;
                    $buildInfo = json::readFile($apiUrl . $path,false);
                    $fileName = $buildInfo['downloads']['application']['name'];
                    $path .= "/downloads" . "/" . $fileName;
                    downloader::downloadFile($apiUrl . $path, $libraryDir . $path);
                    if(hash_file('sha256',$libraryDir . $path) === $buildInfo['downloads']['application']['sha256']){
                        json::writeFile($libraryDir . $projectsPath . ".json",$localProjectInfo,true);
                        json::writeFile($libraryDir . $versionsPath . ".json",$localVersionInfo,true);
                        json::writeFile($libraryDir . $buildsPath . ".json",$localBuildsInfo,true);
                        json::writeFile($libraryDir . $buildPath . ".json",$buildInfo,true);
                        return true;
                    }
                    else{
                        mklog('warning','Failed to download ' . $fileName,false);
                        if(is_file($libraryDir . $path)){
                            unlink($libraryDir . $path);
                        }
                    }
                }
            }
        }
        return false;
    }
    public static function init():void{
        $defaultSettings = array(
            "apiUrl" => "https://api.papermc.io/v2",
            "libraryDir" => "mcservers\\library\\papermc"
        );
        foreach($defaultSettings as $settingName => $settingValue){
            settings::set($settingName,$settingValue,false);
        }
    }
    public static function setSetting(string $settingName, mixed $settingValue, bool $overwrite):bool{
        return settings::set($settingName,$settingValue,$overwrite);
    }
}