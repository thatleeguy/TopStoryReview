<?php

function ShowWords(){
  $Data = Query("SELECT Headline FROM Story LEFT JOIN FeedCategory ON FeedCategory.FeedCategoryID = Story.FeedCategoryID WHERE PubDate > DATE_SUB(NOW(), INTERVAL 7 DAY)");
  $Text = '';
  foreach($Data as $Story){
    $Text.=' '.$Story['Headline'];
  }
  $Text=ScoreWords($Text);
  OutputJSON($Text);
}

function ListSources(){
  $Data = Query("SELECT * FROM Feed");
  OutputJSON($Data);
}

function TopStoryFeed($Category){
  VerifyDirectoryStructure();
  
  if(
    $Category=='all'||
    $Category==false
  ){
    $Path = Query("SELECT * FROM FeedCategory WHERE Name LIKE 'All'");
  }else{
    global $ASTRIA;
    $SafePath = mysqli_real_escape_string($ASTRIA['databases']['astria']['resource'],$Category);
    $Path = Query("SELECT Path FROM FeedCategory WHERE Path LIKE '".$SafePath."'");
    if(!(isset($Path['0']))){
      header('Location: /categories');
      exit;
    }
    
  }
  
  if(
    isset($Path[0])&&
    isset($Path[0]['Name'])&&
    $Path[0]['Name']=='All'
  ){
    $Path[0]['Path']='all';
  }
  
  $FilePath = 'archive/'.$Path[0]['Path'];
  $ArchivePath = 'archive/'.$Path[0]['Path'].'/'.date('Y').'/'.date('m').'/'.date('d').'/'.date('H').':00:00.json';
  
  $Archive = ReadJSONArchive($ArchivePath);
  if($Archive){
    OutputJSON($Archive);
    return;
  }
  
  if(
    $Category=='all'||
    $Category==false
  ){
    $Data = Query("SELECT Headline FROM Story WHERE PubDate > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
  }else{
    global $ASTRIA;
    $FeedPath = mysqli_real_escape_string($ASTRIA['databases']['astria']['resource'],$Category);
    $FeedPath = strtolower($FeedPath);
    $Data = Query("SELECT Headline FROM Story LEFT JOIN FeedCategory ON FeedCategory.FeedCategoryID = Story.FeedCategoryID WHERE FeedCategory.Path LIKE '".$FeedPath."' AND PubDate > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
  }
  $Headlines = array();
  foreach($Data as $Headline){
    $Headlines[]=$Headline['Headline'];
  }
  
  $ParseCount = count($Data);

  $Headlines = PickBest3($Headlines,5);
  foreach($Headlines as &$Headline){
    global $ASTRIA;
    $CleanHeadline = mysqli_real_escape_string($ASTRIA['databases']['astria']['resource'],$Headline['element']);
    $Story = Query("SELECT * FROM Story LEFT JOIN FeedCategory ON FeedCategory.FeedCategoryID = Story.FeedCategoryID LEFT JOIN FeedSource ON FeedSource.FeedSourceID = Story.SourceID WHERE `Headline` LIKE '%".$CleanHeadline."%' ORDER BY StoryID DESC LIMIT 1");
    if(isset($Story[0])){
      $Story=$Story[0];
      $PubDate = strtotime($Story['PubDate']);
      if(
        ($PubDate > time())||
        ($PubDate <= 0)
      ){
        $PubDate = strtotime($Story['FetchDate']);
      }
      $Headline['element']=array(
        'Headline'   => $Story['Headline'],
        'PubDate'    => $PubDate,
        'Link'       => $Story['Link'],
        'SourceName' => $Story['Name'],
        'SourceLogo' => $Story['LogoURL']
      );
    }
    
    $Keywords = mysqli_real_escape_string($ASTRIA['databases']['astria']['resource'],$Headline['keywords']);
    $Keywords = explode(',',$Keywords);
    $SQL = "SELECT Headline,PubDate,FeedSource.Name as 'Source',Link FROM Story LEFT JOIN FeedCategory ON FeedCategory.FeedCategoryID = Story.FeedCategoryID LEFT JOIN FeedSource ON FeedSource.FeedSourceID = Story.SourceID WHERE ( PubDate > NOW() - INTERVAL 1 WEEK ) ";
    foreach($Keywords as $Keyword){
      $SQL.=" AND `Headline` LIKE '%".$Keyword."%' ";
    }
    $SQL.="ORDER BY StoryID DESC LIMIT 5";
    $Related = Query($SQL);
    foreach($Related as &$RelatedStory){
      $RelatedStory['PubDate'] = strtotime($RelatedStory['PubDate']);
    }
    
    $Headline['related'] = $Related;
  }
  
  $Headlines['message']='Parsed '.$ParseCount.' stories to make this list. Check back each hour for fresh content.';
  
  foreach($Headlines as $Index => $Headline){
    if(isset($Headline['related'])){
      if(count($Headline['related'])<2){
        if(!($Category=='science')){
          unset($Headlines[$Index]);
        }
      }
    }
  }
  
  WriteJSONArchive($ArchivePath,$Headlines);
  
  foreach($Headlines as $Headline){
    //pd($Headline);
    $Data = WriteFileArchive($FilePath,$Headline);
    //pd($Data);
    //echo '<hr>';
  }
  
  OutputJSON($Headlines);
  
}

function WriteFileArchive($ArchivePath,$Data){
  if(!(
     (isset($Data['element']))||
     (isset($Data['element']['Headline']))
  )){
    return;
  }
  
  $Path = strtolower($Data['element']['Headline']);
  $Path = preg_replace("/[^A-Za-z0-9 ]/", '', $Path);
  $Path = str_replace(' ','-',$Path);
  
  $Path = $ArchivePath.'/'.$Path.'.json';
  $Data = json_encode($Data,JSON_PRETTY_PRINT);
  return file_put_contents($Path,$Data);
}

function ListCategories(){
  $Categories = GetTSRCategories();
  $Output = array();
  
  foreach($Categories as $Category){
    $Output[$Category['FeedCategoryID']]=array(
      'Name'        => $Category['Name'],
      'Description' => $Category['Description'],
      'FeedLink'    => 'https://api.topstoryreview.com/feed/'.$Category['Path']
    );
  }
  
  OutputJSON($Output);
}

function GetTSRCategories(){
  $Categories = Query('SELECT * FROM FeedCategory WHERE ParentID IS NULL');
  return $Categories;
}

function VerifyDirectoryStructure(){
  $Categories = GetTSRCategories();
  foreach($Categories as $Category){
    $Subpath = $Category['Path'];
    if($Category['Name']=='All'){
      $Subpath = 'all';
    }
    $Paths=array(
      'archive/'.$Subpath,
      'archive/'.$Subpath.'/'.date('Y'),
      'archive/'.$Subpath.'/'.date('Y').'/'.date('m'),
      'archive/'.$Subpath.'/'.date('Y').'/'.date('m').'/'.date('d')
    );
    foreach($Paths as $Path){
      if(!(file_exists($Path))){
        mkdir($Path);
      }
    }
  }
}

function WriteJSONArchive($Path,$Data){
  $Path = $_SERVER['DOCUMENT_ROOT'].'/'.$Path;
  $Path = str_replace('//','/',$Path);
  
  $Data = json_encode($Data,JSON_PRETTY_PRINT);
  return file_put_contents($Path,$Data);
}

function ReadJSONArchive($Path){
  $Path = $_SERVER['DOCUMENT_ROOT'].'/'.$Path;
  $Path = str_replace('//','/',$Path);
  
  if(!(file_exists($Path))){
    return false;
  }
    
  $Data = file_get_contents($Path);
  
  if($Data == false){
    return false; 
  }
  
  $Data = json_decode($Data,true);
  
  return $Data;
}
