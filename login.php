<!DOCTYPE html>

<html>
  <head>
    <title>Tour Editor Login</title>
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8" >
    <link rel="stylesheet" href="../../style/style.css" type="text/css">
    <script type="text/javascript">
    function login()
    {
      var user = document.getElementById("user").value;
      var pass = document.getElementById("pass").value;
      var out = "user=" + user +"&pass=" + pass;
      var rq = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
      rq.open("POST", "admin.php?q=login");
      rq.onreadystatechange = function() {
        if(rq.status == 200 && rq.readyState == 4)
        {
          if(rq.responseText == "true")
            document.location = "toureditor.php";
          else
            document.getElementById("result").innerText = "Invalid Login";
        }
      }
      rq.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
      rq.send(out);
      return false;
    }

    function init()
    {
      var frm = document.getElementById("submit");
      frm.onsubmit = login;
    }
    </script>
  </head>
  <body onload="init();">
    <div class="center">
      <div>
        <b>Mercer University Virtual Tour Editor</b><br />
        Please Login<br />
        <form id="submit" method="POST">
          Username: <input id="user" type="text" name="username" /><br />
          Password: <input id="pass" type="password" name="password" /><br />
          <input type="submit" value="Login" id="login">
        </form>
        <div id="result"></div>
	<br /><br />
      </div>
      <div class="paragraph">
        <b>Instructions for using the tour editor:</b><br />
        Clicking a marker shows an infowindow above or near the marker.<br />
        This infowindow contains fields which allow the user to edit information about it.<br />
	It can be closed by pressing the "x" in the upper right corner<br />
        The user can change the name shown to the user of the POI by typing the new name into the name field and then clicking "Update Name"<br />
        The user can change the picture associated with the POI by clicking "Choose File" and choosing the picture they want.<br />
        The user can delete a path by clicking the "Delete Path" which is adjacent to the end point.<br /><br />
	Adding a new path is a more involved process. It can be initiated by clicking "Add Path", and closing the infowindow.<br />
          Clicking on the map will create a waypoint from the previous marker to where the user clicked.<br />
          Adding waypoints can be repeated as many times as desired.<br />
          Clicking a different marker will end the path at that marker.<br />
          Please note, there can only be one path between two POI at a time.
          If a path already exists between the two markers, it must be deleted before initiating the process.<br /><br />
	If the user is not adding a new path, clicking the map will create a new marker and POI.<br />
	Right clicking a marker will delete it and the associated POI<br /><br />
	Logging out of the tour editor is done by closing the browser. (need to implement logout button)
      </div>
    </div>
  </body>
</html>
