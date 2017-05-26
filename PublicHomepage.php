<?php

function PublicHomepage(){
  global $Title,$Category;
  if(path(0)==false){
    $Title    = "Here's The Latest";
    $Category = Query("SELECT * FROM FeedCategory WHERE Name LIKE 'Featured'")[0];
  }else{
    $Categories = Query('SELECT * FROM FeedCategory');
    $Category = null;
    //$Path  = mysqli_real_escape_string($ASTRIA['databases']['astria']['resource'],path(0));
    foreach($Categories as $ThisCategory){
      if(path(0)==$ThisCategory['Path']){
        $Category = $ThisCategory;
        break;
      }
    }
    if($Category==null){
      $Title = 'Not Found';
    }else{
      $Title = $Category['Name'];
    }
  }
  TemplateBootstrap4($Title,'PublicHomepageBodyCallback();');
}

function PublicHomepageBodyCallback(){
  global $Title,$Category;
  ?>

  <div class="container">
    <div class="row">
      <div class="col-xs-12 col-md-9">
        <h1><?php echo $Title; ?></h1>
        <?php
          if($Category==null){
            //TODO log these
            ?>
            <p>Sorry about that.</p>
            <?php
          }else{
            $Data = Query("SELECT Headline FROM Story WHERE FeedCategoryID = ".intval($Category['FeedCategoryID']." AND PubDate > DATE_SUB(NOW(), INTERVAL 24 HOUR)"));
            
            $Headlines = array();
            foreach($Data as $Headline){
              $Headlines[]=$Headline['Headline'];
            }
            
            $Headlines = PickBest($Headlines,5);
            foreach($Headlines as $Headline){
              ?>
        
                <p><?php echo $Headline; ?></p>
        
              <?php
            }
            
          }
        ?>
      </div>
      <div class="col-xs-12 col-md-3">
        <h2>Archive</h2>
        <?php
          $Data = Query("SELECT * FROM HeadlineArchive WHERE FeedCategoryID = ".$Category['FeedCategoryID']." ORDER BY DateTime DESC");
          foreach($Data as $Entry){
          //TODO these labels could be more interesting. Maybe just the top three words?  
          ?>
            <p><a href="<?php echo $Entry['Permalink']; ?>"><?php ago($Entry['DateTime']); ?></a></p>
          <?php
          }
        ?>
      </div>
    </div>
  </div>

  <?php
}
