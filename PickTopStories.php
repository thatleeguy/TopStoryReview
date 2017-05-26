<?php

function PickTopStories($Category = 'All',$NumberOfStories = 5){
  $Output = '';
  if($Category==null){
    //TODO log these
    $Output.='<p>Sorry about that.</p>';
  }else{
    if($Category['Name']=='All'){
      $Data = Query("SELECT Headline FROM Story WHERE PubDate > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    }else{
      $Data = Query("SELECT Headline FROM Story WHERE FeedCategoryID = ".intval($Category['FeedCategoryID'])." AND PubDate > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    }
    $Headlines = array();
    foreach($Data as $Headline){
      $Headlines[]=$Headline['Headline'];
    }

    $Headlines = PickBest($Headlines,$NumberOfStories);
    $Output.='<p><i>Out of '.count($Data).' headlines in category \''. $Category['Name'].',\' I picked these '.$NumberOfStories.' for you.</i></p>';
    foreach($Headlines as $Headline){
      $Output.='<p>'.$Headline.'</p>';
    }
  }
  $Preview=array();
  return array(
    'Preview' => $Preview,
    'Content' => $Output
  );
}

function PickTopStories2($Category = 'All',$NumberOfStories = 5){
  if($Category=='All'){
    $Category = Query("SELECT * FROM FeedCategory WHERE Name LIKE 'All'")[0];
  }
  $Output = '';
  if($Category==null){
    //TODO log these
    $Output.='<p>Sorry about that.</p>';
  }else{
    if($Category['Name']=='All'){
      $AllHeadlines = Query("SELECT Headline FROM Story WHERE PubDate > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    }else{
      $AllHeadlines = Query("SELECT Headline FROM Story WHERE FeedCategoryID = ".intval($Category['FeedCategoryID'])." AND PubDate > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    }
    $Headlines = array();
    foreach($AllHeadlines as $Headline){
      $Headlines[]=$Headline['Headline'];
    }

    $BannedWords = array();
    $FinalHeadlines = array();
    //$FinalHeadlines = PickBest($Headlines,$NumberOfStories);
    for($i = 1; $i <= $NumberOfStories; $i++){
      $Phrase=array();
      for($i = 1; $i <= 3; $i++){
        
        //Make a temp list and add any headlines which do not contain banned words.
        foreach($Headlines as $Headline){
          $Add = true;
          foreach($BannedWords as $BannedWord){
            if(!(strpos(strtolower($Headline),strtolower($BannedWord))==false)){
              $Add=false;
            }
          }
          if($Add){
            
            //Make sure each head line is not missing any of the phrase words
            $Good = true;
            foreach($Phrase as $GoodWord){
              if(strpos(strtolower($Headline),strtolower($BannedWord))==false){
                $Good = false;
              }
            }
            if($Good){
              $TempHeadlines[]=$Headline;
            }
            
          }
        }

        $Slug = implode(' ',$TempHeadlines);
        $MostImportantWords = CondenseGetWordScores($Slug);
        
        //add to phrase, the next important word which is not already in phrase
        foreach($MostImportantWords as $ImportantWord){
          if(!(in_array($ImportantWord,$Phrase))){
            pd($ImportantWord);
            $Phrase[]      = $ImportantWord;
            $BannedWords[] = $ImportantWord;
            break;
          }
        } 
      }
      pd($Phrase);
    }
    
    
    //$Output.='<p><i>Out of '.count($AllHeadlines).' headlines in category \''. $Category['Name'].',\' I picked these '.$NumberOfStories.' for you.</i></p>';
    //foreach($FinalHeadlines as $Headline){
      //$Output.='<p>'.$Headline.'</p>';
    //}
  }
  $Preview=array();
  return array(
    'Preview' => $Preview,
    'Content' => $Output
  );
}

