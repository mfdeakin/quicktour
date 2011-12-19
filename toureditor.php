<!DOCTYPE html>

<html>
  <head>
    <meta name="viewport" content="initial-scale=1.0, user-scalable=no" />
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8" >
    <title>Tour Editor</title>
    <link rel="stylesheet" href="../../style/style.css" type="text/css">
	<script type="text/javascript" src="http://www.google.com/jsapi"></script>
    <script type="text/javascript">
      var map, points, paths, iwnd, definfo;

      google.load("maps", "3", {other_params:'sensor=false', callback: init});

      //Initializes the map and the POI
      function init()
      {
        checklogin();
        definfo = document.getElementById("infownd");
        var mapopts = {
          zoom: 17,
          center: new google.maps.LatLng(32.83012529874671, -83.64971995353699),
          mapTypeId: google.maps.MapTypeId.SATELLITE,
        };
        window.onclose = logout;
        window.onbeforeunload = logout;
        map = new google.maps.Map(document.getElementById("gmap"), mapopts);
        points = [];
        iwnd = new google.maps.InfoWindow({
          pane: "mapPane",
        });
        google.maps.event.addListener(iwnd, 'domready', updateinfo);
        google.maps.event.addListener(map, 'click', mapclick);
        loadpoi();
      }

      function loadpoi()
      {
        var rq = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
        rq.onreadystatechange = addpoi(rq);
        rq.open("GET", "admin.php?q=initpos", true);
        rq.send(null);
      }

      function loadpaths()
      {
        for(id in points)
        {
          var rq = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
          rq.onreadystatechange = addpaths(rq, id);
          rq.open("GET", "admin.php?q=getpaths&id=" + id, true);
          rq.send(null);
        }
      }

      function addpoi(rq)
      {
        return function() {
          if(rq.readyState == 4 && rq.status == 200 && errcheck(rq.responseText))
          {
            //Input format id1|name|lat|lng|pic
            //             id2|name|lat|lng|pic ...
            var text = rq.responseText.split("\n");
            for(i in text)
            {
              text[i] = text[i].split("|");
              var id = parseInt(text[i][0]);
              var lat = parseFloat(text[i][2]);
              var lng = parseFloat(text[i][3]);
              createmarker(id, lat, lng, text[i][1], text[i][4]);
            }
            loadpaths();
          }
        }
      }

      function addpaths(rq, id)
      {
        return function() {
          if(rq.readyState == 4 && rq.status == 200 && rq.responseText != "" && errcheck(rq.responseText)) {
            //Input Format: dest1:lat1,lng1|lat2,lng2|...:enabled
            //                dest2:lat1,lng1|...:enabled
            text = rq.responseText.split("\n");
            for(i in text)
            {
              text[i] = text[i].split(":");
              var dest = parseInt(text[i][0]);
              text[i][1] = text[i][1].split("|");
              var path = [];
              for(j in text[i][1])
              {
                text[i][1][j] = text[i][1][j].split(",");
                var lat = parseFloat(text[i][1][j][0]);
                var lng = parseFloat(text[i][1][j][1]);
                path.push(new google.maps.LatLng(lat, lng));
              }
              points[id].paths[dest] = new google.maps.Polyline({
                map: map,
                path: path,
                strokeColor: "#FF0000",
                strokeOpacity: (text[i][2] == "1") ? 1.0 : 0.5
              });
              //Paths are bidirectional
              points[dest].paths[id] = points[id].paths[dest];
            }
          }
        }
      }

      function createmarker(id, lat, lng, place, picsrc)
      {
        points[id] = {
          marker: new google.maps.Marker({
            map: map,
            position: new google.maps.LatLng(lat, lng)
          }),
          place: place,
          paths: [],
          img: picsrc,
        };
        google.maps.event.addListener(points[id].marker, 'click', clickmarker(id));
        google.maps.event.addListener(points[id].marker, 'rightclick', deletemarker(id));
      }

      function createnewmarker(pos)
      {
        //We need to get the markers id from the database, but want to display the marker immediately
        //So create a marker, and tell the database to add this location, and then get the id
        var marker = new google.maps.Marker({
          map: map,
          position: pos
        });
        var rq = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
        rq.onreadystatechange = pushmarker(rq, marker);
        rq.open("POST", "admin.php?q=addpoi&lat=" + pos.lat() + "&lng=" + pos.lng(), true);
        rq.send(null);
      }

      function pushmarker(rq, marker)
      {
        return function()
        {
          if(rq.readyState == 4 && rq.status == 200 && errcheck(rq.responseText))
          {
            var id = parseInt(rq.responseText);
            points[id] = {
              marker: marker,
              place: "",
              paths: [],
              img: "",
              imgnew: null,
              imgchange: false
            };
            google.maps.event.addListener(points[id].marker, 'click', clickmarker(id));
            google.maps.event.addListener(points[id].marker, 'rightclick', deletemarker(id));
          }
        }
      }

      function showinfo(event, id)
      {
        sessionrefresh();
        iwnd.set("cur_id", id);
        iwnd.set("openonce", false);
        iwnd.setContent(definfo);
        iwnd.open(map, points[id].marker);
      }

      //Used for formatting the infowindow for each POI
      //Tells it the name of the place, the places that are connected to it, and the picture associated with it
      function updateinfo()
      {
        var id = iwnd.get("cur_id");
        if(!iwnd.get("openonce"))
        {
          iwnd.set("openonce", true);
          iwnd.open(map, points[id].marker);
        }
        var pic = document.getElementById("infoimp");
        pic.src = points[id].img != "" ? points[id].img : "/campustour/images/map/thumbnails/defpic.png";
        var name = document.getElementById("infoname");
        name.value = points[id].place;
        var fil = document.getElementById("infoimg");
        fil.onchange = changepic(id);
		fil.value = "";
        var btn = document.getElementById("infobtn");
        //We only want one event handler, so just set it directly
        //Otherwise bad things happen (renaming all the POI previously opened...)
        btn.onclick = updatepoi(id);
        var addpath = document.getElementById("infopathadd");
        //See previous
        addpath.onclick = pathstart(id);
        var table = document.getElementById("infopaths");
        //Clear the table
        while(table.firstChild)
          table.removeChild(table.firstChild);
        //Fill it back up with the new information
        for(i in points[id].paths)
        {
          addpathrow(table, id, i);
        }
      }

      //Tells the database the name of the POI and the picture to use
      function updatepoi(id)
      {
        return function()
        {
          var name = document.getElementById("infoname").value;
          var pic = document.getElementById("infoimg").value;
          var rq = window.XMLHttpRequest ? new XMLHttpRequest : new ActiveXObject("Microsoft.XMLHTTP");
          rq.open("POST", "admin.php?q=updatepoi&id=" + id + "&place=" + name);
          rq.send(null);
          points[id].place = name;
        }
      }

      function changepic(id)
      {
        return function(e)
        {
          e.stopPropagation();
          e.preventDefault();
          e.target.className = e.type == "dragover" ? "hover" : "";
          var file = e.target ? e.target.files[0] : e.dataTransfer.files[0];
          //Not certain why bothering with IE when IE doesn't support HTML5 file stuff
          var rq = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
          rq.open("POST", "admin.php?q=uploadpic&id=" + id);
          //Update the picture a very stupid way (Client -> Server -> Client)
          rq.onreadystatechange = function() {
            if(rq.readyState == 4 && rq.status == 200 && rq.responseText != "")
            {
              var img = document.getElementById("infoimp");
              img.src = rq.responseText;
            }
          };
          rq.setRequestHeader("X_FILENAME", file.name);
          rq.send(file);
        }
      }
      function donothing() {
      }

      var pathstate = {
        //Whether clicking the map creates a marker or adds to the path (0, 1)
        clickmode: 0,
        //The id of the source marker
        id: 0,
        //The path so far
        path: [],
        //The google maps path for visualization purposes
        gline: null
      };

      //Begins the construction of a path
      function pathstart(id)
      {
        return function()
        {
          pathstate.clickmode = 1;
          pathstate.id = id;
          pathstate.path = [points[id].marker.getPosition()];
          pathstate.gline = new google.maps.Polyline({
            map: map,
            path: [],
            strokeColor: "#0000FF"
          });
        };
      }

      //Adds an element to the path being constructed
      function pathadd(event)
      {
        sessionrefresh();
        pathstate.path.push(event.latLng);
        pathstate.gline.setPath(pathstate.path);
      }

      //Fairly annoying function
      //End the path at the destination node,
      //Tell the server to update the database to include the new path
      //Delete the polyline used for drawing the path
      //Create a new polyline from the source, to the start of the path, over the path, to the destination
      function pathend(dest)
      {
        //Can't have a path from a node to itself, and can't have more than one path to a single destination
        if(pathstate.id == dest || points[id].paths[dest])
          return;
        //Let the user do normal stuff now
        pathstate.clickmode = 0;

        //Just so the code looks nicer
        var src = pathstate.id;
      
        pathstate.path.push(points[dest].marker.getPosition());

        //Tell the server about the path
        //Output format:
        //cnt=val&lat1=val&lng1=val&lat2=val&...
        var newpath = "cnt=" + pathstate.path.length;
        pathstate.gline.setMap(null);
        for(i in pathstate.path)
        {
          newpath += "&lat" + (parseInt(i) + 1) + "=" + pathstate.path[i].lat();
          newpath += "&lng" + (parseInt(i) + 1) + "=" + pathstate.path[i].lng();
        }
        //Send the path points!
        var rq = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
        rq.open("POST", "admin.php?q=addpath&poi1=" + src + "&poi2=" + dest, true);
        rq.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        rq.send(newpath);
        //Create a new polyline for display purposes
        points[src].paths[dest] = new google.maps.Polyline({
          map: map,
          path: pathstate.path,
          strokeColor: "#FF0000"
        });
        points[dest].paths[src] = points[src].paths[dest];
        var table = document.getElementById("infopaths");
        addpathrow(table, src, dest);
      }

      function mapclick(event)
      {
        if(!pathstate.clickmode)
          createnewmarker(event.latLng);
        else
          pathadd(event);
      }

      function clickmarker(id)
      {
        return function(event)
          {
            if(pathstate.clickmode == 0)
              showinfo(event, id);
            else
              pathend(id);
          }
      }

      function deletemarker(id)
      {
        return function(event)
          {
            //Delete the marker
            points[id].marker.setMap(null);
            //Delete the paths from this marker
            for(i in points[id].paths)
            {
              points[id].paths[i].setMap(null);
              //Delete the path from the destination node as well
              points[i].paths.splice(id, 1);
            }
            //Delete the array element
            points.splice(id, 1);
            //Delete all paths to this point
            var rq = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
            rq.open("POST", "admin.php?q=delmarker&id=" + id, true);
            rq.send(null);
          }
      }

      //Deletes the path from the database and the page itself
      function deletepath(poi1, poi2, table, row)
      {
        return function()
        {
          var rq = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
          rq.open("POST", "admin.php?q=delpath&poi1=" + poi1 + "&poi2=" + poi2, true);
          rq.send(null);
          table.removeChild(row);
          points[poi1].paths[poi2].setMap(null);
          //Free up the associated memory
          points[poi1].paths.splice(poi2, 1);
          points[poi2].paths.splice(poi1, 1);
        }
      }

      function checklogin()
      {
        var rq = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTPP");
        //Can't be asynchronous because we need to check for login immediately
        rq.open("GET", "admin.php?q=isloggedin", false);
        rq.onreadystatechange = function() {
          if(rq.readyState == 4 && rq.status == 200)
          {
            if(rq.responseText == "")
            {
              showlogin();
            }
          }
        };
        rq.send(null);
      }

      function showlogin()
      {
        document.location = "login.php";
      }

      function logout()
      {
        var rq = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
        rq.open("POST", "admin.php?q=logout");
        rq.send(null);
      }
      
      //Creates a row for the points path table
      function addpathrow(table, id, dest)
      {
        var row = document.createElement("tr");
        var cell = document.createElement("td");
        cell.appendChild(document.createTextNode(points[dest].place));
        row.appendChild(cell);
        cell = document.createElement("td");
        var del = document.createElement("a");
        del.appendChild(document.createTextNode("Delete Path"));
        del.href = "#";
        addevent(del, 'click', deletepath(id, dest, table, row), false);
        cell.appendChild(del);
        row.appendChild(cell);
        table.appendChild(row);
      }
      
      //Returns false if an error occured
      function errcheck(response)
      {
        if(response.indexOf("Error") != -1)
        {
          if(response == "Error: Not logged in")
            showlogin();
          return false;
		}
        return true;
      }

      function addevent(el, ev, handle, onbubble)
      {
        if(el.addEventListener)
          el.addEventListener(ev, handle, onbubble);
        else if(el.attachEvent)
          el.attachEvent(ev, handle);
      }

      //Make sure the session doesn't timeout even when the user is doing stuff
      function sessionrefresh()
      {
        var rq = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
        rq.open("GET", "admin.php?q=refresh");
        rq.send(null);
      }
      
    </script>
  </head>
  <body>
    <div id="gmap"></div>

    <!-- Not shown; used for the info window and file upload -->
    <div class="hidden">
      <div id="infownd">
        Name: <input id="infoname" name="infoname" type="text" value="" /><button id="infobtn">Update Name</button><br />
        Picture: <input id="infoimg" type="file" name="infoimg" /><br />
        <img id="infoimp" src="/campustour/images/map/thumbnails/defpic.png" alt="No image" /><br />
        Paths: <a id="infopathadd" href="#">Add Path</a><br />
        <table id="infopaths">
        </table>
      </div>
    </div>
  </body>
</html>
