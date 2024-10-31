/*****************************************************
** Name:        prayer247.js
** Author:      David Thompson
** Version:     2.2
** Plugin:      Prayer 24-7
** Description: Handles prayer timeslot sign-ups including AJAX requests back to server-side plugin
*****************************************************/

// Declare our JQuery Alias
jQuery( 'document' ).ready( function( $ ) {
    var selectedslots = [];
//    var startdatetime = new Date( 2020, 5, 15, 8 );        // yr, month-1, date, hour
    const oneDay = 24 * 60 * 60 * 1000;
    
    // p247_vars holds the localized array of variables passed into the script by Wordpress (see php file)
    var startdatetime = new Date( 
        parseInt ( p247_vars.syear ), 
        parseInt ( p247_vars.smonth ) - 1, 
        parseInt ( p247_vars.sdate ), 
        parseInt ( p247_vars.shour ) );        // yr, month-1, date, hour
    var enddatetime = new Date( 
        parseInt ( p247_vars.eyear ), 
        parseInt ( p247_vars.emonth ) - 1, 
        parseInt ( p247_vars.edate ), 
        parseInt ( p247_vars.ehour ) );        // yr, month-1, date, hour
    
    // Find the Monday at the start of the week and record how many days must be added
    var stdayoffset = startdatetime.getDay() - 1;
    if (stdayoffset == -1) {
        stdayoffset = 6;
    }
    // Find the Sunday at the end of the week and record how many days must be added
    var endayoffset = 7 - enddatetime.getDay();
    if (endayoffset == 7) {
        endayoffset = 0;
    }
    
    var stzero = new Date( startdatetime.getTime() );
    stzero.setHours(0);
    stzero.setMinutes(0);
    stzero.setSeconds(0);
    stzero.setMilliseconds(0);
    var enzero = new Date( enddatetime.getTime() );
    enzero.setHours(0);
    enzero.setMinutes(0);
    enzero.setSeconds(0);
    enzero.setMilliseconds(0);
    var totaldays = Math.round(Math.abs((enzero - stzero) / oneDay)) + 1 + stdayoffset + endayoffset;
    
    var selslots = new Array(totaldays);                // The array of bools showing which slots are temporarily selected
    for (var i = 0; i < selslots.length; i++) {
        selslots[i] = new Array(24);
        for (var j = 0; j < selslots[i].length; j++ ) {
            selslots[i][j] = false; 
        }
    }
    
    var hrecall = new Array(totaldays);                 // The array of strings showing slot status obtained from server
    for (var i = 0; i < hrecall.length; i++) {
        hrecall[i] = new Array(24);
        for (var j = 0; j < hrecall[i].length; j++ ) {
            hrecall[i][j] = 'disabled';   // unknown, disabled, empty, chosen, selected
        }
    }
    
    var opengrid = null;                                // Stores which day is expanded for when a different day is clicked
    var openindex = -1;
    
    var daystatus = new Array(totaldays);               // Array of days, to keep track of status and colour accordingly
    var trecall = new Array(totaldays);
    for (var i = 0; i < daystatus.length; i++) {
        daystatus[i] = new Array(2);
        daystatus[i][0] = 'empty';                      // The status of this day
        daystatus[i][1] = 0.0;                          // The proportion of timeslots in this day that are chosen
        
        trecall[i] = new Array(2);                      // A mirror array to save status when day is expanded or selected
        trecall[i][0] = 'empty';
        trecall[i][1] = 0.0;
    }
    
    
    // Disable Select button as no timeslots are selected on page load
    var enb = document.getElementById('p247selectbtnid');
    if ( enb ) {                
        enb.disabled = true;                    
    }
    
    //******************************************
    // Initialise grids colours via AJAX request
    //******************************************
    var iobj = new Object();
    $.ajax( {
        url: p247_ajax_obj.ajax_url,
        type: 'POST',
        data: {
            action: 'submit_selection',
            security: p247_ajax_obj.security,
            'ajax_mode': '10',          // mode for 'read only'
            'ajax_name': "",
            'ajax_email': "",
            'ajax_slots': "",
        },
        success: function( results ) {
            // Server replied to say data was transferred successfully
            iobj = JSON.parse( results );
            console.log('Success from the AJAX request!')
            console.log(results)

            // Step through returned array placing slot status in correct place.
            // Values show the number of users who have selected each slot althoughh this info is not current used.
            for( var ds = 0; ds < iobj.length; ds++ ) {
                for( var hs = 0; hs < iobj[ds].length; hs++ ) {
                    var tsc = parseInt( iobj[ds][hs] );
                    
                    if (tsc > 0) {
                        hrecall[ds + stdayoffset][hs] = 'chosen';
                    }
                    else if (tsc == 0) {
                        hrecall[ds + stdayoffset][hs] = 'empty';
                    }
                    else {
                        hrecall[ds + stdayoffset][hs] = 'disabled';
                    }
                }
            }
            
            // Remove historic timeslots and those outside of start and end times
            var nowdt = new Date();     // With no arguments supplied, object is created with current time
            nowdt.setMinutes(0);        // Set 'now' to be on the hour
            nowdt.setSeconds(0);
            nowdt.setMilliseconds(0);

            var hdt = new Date( startdatetime.getTime() - stdayoffset * 24*60*60*1000 );
            hdt.setHours(0);            // Time should be on the hour anyway, but this makes sure
            hdt.setMinutes(0);
            hdt.setSeconds(0);
            hdt.setMilliseconds(0);
            for ( var histd = 0; histd < hrecall.length; histd++ ) {
                for (var histt = 0; histt < hrecall[histd].length; histt++ ) {                    
                    if ( nowdt > hdt || hdt < startdatetime || hdt > enddatetime ) {
                        // slot is historic or before start time or after end time
                        hrecall[histd][histt] = 'disabled';
                    }
                    hdt.setTime( hdt.getTime() + 60*60*1000 );      // Step forward in 1-hour jumps
                }
            }
            
            // Color days based on saved status
            updatedaystatus( daystatus, hrecall );
            updatedayspaint( daystatus );
                        
        },
        error: function( erdata ) {
            // Server replied to say that an error occured
            console.log( 'Returned error from AJAX' );
            console.log( erdata );
        }
    });

    //****************************************
    // Event handler for clicking on p24x grid
    //****************************************
    var p24xgrid = document.getElementById('p24xgridid'); // find the images container
    if (p24xgrid) {
        var tslots = [].slice.call(p24xgrid.querySelectorAll('.p24xgriditem'), 0); // get all grid items inside container, and convert to array

        // add an event listener that will handle all clicks inside container
        p24xgrid.addEventListener('click', function(e) {  
            // find the index of the clicked target from the grid items array
            var index = tslots.indexOf(e.target)
            var timegrid;

            if(index !== -1) { // if not 'no item was clicked'
                console.log(index);
                if ( daystatus[index][0] != 'disabled' ) {                // eg, slot precedes the current time
                    if ( daystatus[index][0] != 'selected' && daystatus[index][0] != 'expanded' ) {
                        // save the current status of the slot so we can deselect it later
                        trecall[index][0] = daystatus[index][0];
                        trecall[index][1] = daystatus[index][1];
                    }    
                    
                    // Collapse any other time grid
                    timegrid = document.getElementById('p24xtimegridid');
                    if (timegrid && openindex != -1) {
                        if (someslotsselected(openindex, selslots)) {
                            // if there were selected slots, show this day as selected
                            // openindex is the ref of the expanded day, NOT the one that was just clicked on
                            daystatus[openindex][0] = 'selected';
                        }
                        else {
                            // there were no seleceted slots, so return to saved colour
                            daystatus[openindex][0] = trecall[openindex][0];
                            daystatus[openindex][1] = trecall[openindex][1];
                        }
                        paintthisday( tslots[openindex], daystatus[openindex] );
                        timegrid.remove();                  // renomove this element from the DOM
                    }

                    // If user clicked on the day already expanded, simply close it
                    if (tslots[index] == opengrid) {
                        opengrid = null;
                        openindex = -1;
                    }
                    else {
                        // Else, user clicked on a new day, so expand the new time grid
                        var timehtml = buildtimegrid( tslots[index].id );                       // get the html for the timegrid
                        if ( screen.width > 600 ) {
                            // insert after the parent element, which is the 7-day block
                            tslots[index].parentElement.insertAdjacentHTML('afterend', timehtml);   
                        }
                        else {   
                            // for a mobile, insert after the day (which messes the grid a bit but prevents having to scroll down)
                            tslots[index].insertAdjacentHTML('afterend', timehtml); 
                        }
                        daystatus[index][0] = 'expanded';
                        paintthisday( tslots[index], daystatus[index] );
                        painttimegrid( index, hrecall );                                        // paint the timeslots from server data
                        paintselected( index, selslots );                                       // paint over the currently selected timeslots
                        
                        opengrid = tslots[index];                                               // update the stored open grid
                        openindex = index;
                        
                        // Add event listener to hours grid
                        var hgrid = document.getElementById('p24xtimegridid'); // find the images container
                        if (hgrid) {
                            var hslots = [].slice.call(hgrid.querySelectorAll('.p24xtimeitem'), 0); // get all grid items inside container, and convert to array
                            
                            hgrid.addEventListener('click', function(e) {
                                var hindex = hslots.indexOf(e.target)           // returns the array position of the grid element that was clicked
                                
                                if ( hindex !== -1 ) {
                                    if ( hrecall[index][hindex] !== 'disabled' ) {
                                        // given the slot is not disable, toggle its selection and store new status in array
                                        if ( !selslots[index][hindex] ) {
                                            hslots[hindex].style.backgroundColor = getbgcolor('selected');
                                            selslots[index][hindex] = true;
                                        }
                                        else {
                                            hslots[hindex].style.backgroundColor = getbgcolor( hrecall[index][hindex] );   
                                            selslots[index][hindex] = false;
                                        }
                                        
                                    }
                                }

                            });
                            
                        }

                    }                        
                }
            }
            
            // enable/disable Select button based on whether there are any selected timeslots
            var enb = document.getElementById('p247selectbtnid');
            if ( enb ) {                
                if ( anyselectedslots( selslots ) ) {
                    enb.disabled = false;
                }
                else {
                    enb.disabled = true;                    
                }
            }
        });
    }
           
    
    //********************************
    // Event handler for Select button
    //********************************
    var submitbn = document.getElementById('p247selectbtnid');
    if (submitbn) {
        submitbn.addEventListener('click', function(e) {
            // Set target date/time to be 0:00h on the first day of the first week (Monday)
            var tdate = new Date(startdatetime.getTime());
            tdate.setDate( tdate.getDate() - stdayoffset );
            tdate.setHours(0); 
            tdate.setMinutes(0);
            tdate.setMilliseconds(0);
            
            // Empty existing array of selected date objects
            selectedslots.splice(0, selectedslots.length);
            // Loop over selslots array to build array of selected slots descriptions
            for (var seld = 0; seld < selslots.length; seld++) {
                for (var selh = 0; selh < 24; selh++) {
                    if (selslots[seld][selh]) {
                        // This slot is selected so create new date object and push it to array
                        var savedate = new Date();
                        savedate.setTime(tdate.getTime());
                        selectedslots.push( savedate );
                    }
                    tdate.setTime( tdate.getTime() + (60*60*1000) );        // move target date on by 1 hour
                }
            }
            
            
            if (selectedslots.length) {
 //               selectedslots.sort(function(a, b){return a - b});  // the slots should already by in time order
                console.log(selectedslots);
                
                var sumfieldtxt;
                var sumfield = document.getElementById('p247summlistid');
                if (sumfield) {
                    sumfieldtxt = p247_translate.areyousure + "<br><br><div class=\"p247timeslist\">";
                    //sumfieldtxt = "Are you sure you want to commit to praying at the following times?<br><br><div class=\"p247timeslist\">";

                    var textarray = formattimeslottext( selectedslots );
                    for (ta of textarray) {
                        sumfieldtxt = sumfieldtxt + ta + '<br>';
                    }

                    sumfieldtxt = sumfieldtxt + "</div>";
                    sumfield.innerHTML = sumfieldtxt;
                }
                var sumform = document.getElementById('p247formid');
                if (sumform) {
                    sumform.innerHTML = buildform();
                }

                // Unhide this section of the page
                var nodeList = document.querySelectorAll(".p247summary");
                if (nodeList.length) {
                    nodeList[0].style.display = 'block';
                }
                // Ensure confirm button is enabled
                var confb = document.getElementById('p24xconfirmbtnid');
                if ( confb ) {                
                    confb.disabled = false;                    
                }

                // Disable select button
                var enb = document.getElementById('p247selectbtnid');
                if ( enb ) {                
                    enb.disabled = true;                    
                }

            }
        });
    }
    
    //******************************************************
    // Event handler for User Details form (Confirm button)
    //******************************************************
    var obj = new Object();
    var OKtoSubmit = false;

    // Initialise values
    $( '#p247name' ).val("");
    $( '#p247email' ).val("");
    
    // Event handler for Confirm button
    $(document).on('click','#p247confirmbtnid',function() {     // use the on() form of listener because the object was created after page load
    //$(document).on('submit','#p247form',function() {            // use the on() form of listener because the object was created after page load
        var enteredname = $( '#p247form #p247name' ).val();
        var enteredemail = $( '#p247form #p247email' ).val();
        
        // Disable Confirm button as soon as it is clicked to prevent multiple submissions
        var confb = document.getElementById('p24xconfirmbtnid');
        if ( confb ) {                
            confb.disabled = true;                    
        }
        
        var ajaxslotsarray = getajaxslotsarray( selectedslots );    // build array od date strings suitable for Ajax transfer
        
//        if ( enteredname.length && enteredemail.length) {           // this could be augmented by further checks, eg, valid email address
        if ( validate( enteredname, enteredemail ) ) {           // this could be augmented by further checks, eg, valid email address
            var JSONobj = JSON.stringify( ajaxslotsarray );
            JSONobj = JSONobj.replace(/\"/g, "");                   // remove double-quotes from JSON string
            $.ajax( {
                url: p247_ajax_obj.ajax_url,
                type: 'POST',
                data: {
                    action: 'submit_selection',
                    security: p247_ajax_obj.security,
                    'ajax_mode': '20',                              // code for 'write new values to database' ('10' = read only)
                    'ajax_name': enteredname,
                    'ajax_email': enteredemail,
                    'ajax_slots': JSONobj,
                },
                success: function( results ) {
                    // Server replied to say data was transferred successfully
                    obj = JSON.parse( results );
                    console.log('Success from the AJAX request!')
                    console.log(results)
                    
                    //paint slots based on returned data
                    for( var ds = 0; ds < obj.length; ds++ ) {
                        for( var hs = 0; hs < obj[ds].length; hs++ ) {
                            var tsc = parseInt( obj[ds][hs] );

                            if (tsc > 0) {
                                hrecall[ds + stdayoffset][hs] = 'chosen';
                            }
                            else if (tsc == 0) {
                                hrecall[ds + stdayoffset][hs] = 'empty';
                            }
                            else {
                                hrecall[ds + stdayoffset][hs] = 'disabled';
                            }
                            
                        }
                    }
                    // Collapse any time grid that happened to be open
                    timegrid = document.getElementById('p24xtimegridid');
                    if ( timegrid ) {
                        timegrid.remove();
                        opengrid = null;
                        openindex = -1;
                    }
                    
                    // Remove historic timeslots
                    var nowdt = new Date();     // With no arguments supplied, object is created with current time
                    nowdt.setMinutes(0);       // Set 'now' to be half past the hour
                    nowdt.setSeconds(0);
                    nowdt.setMilliseconds(0);

                    var hdt = new Date( startdatetime.getTime() - stdayoffset * 24*60*60*1000 );
                    hdt.setHours(0);
                    hdt.setMinutes(0);
                    hdt.setSeconds(0);
                    hdt.setMilliseconds(0);
                    for ( var histd = 0; histd < hrecall.length; histd++ ) {
                        for (var histt = 0; histt < hrecall[histd].length; histt++ ) {                    
                            if ( nowdt > hdt || hdt < startdatetime || hdt > enddatetime ) {
                                // slot is historic or before start time or after end time
                                hrecall[histd][histt] = 'disabled';
                            }
                            hdt.setTime( hdt.getTime() + 60*60*1000 );
                        }
                    }

                    // Repaint the day slots
                    updatedaystatus( daystatus, hrecall );
                    updatedayspaint( daystatus );


                    // Swap form for thank you message
                    var sumform = document.getElementById('p247formid');
                    if (sumform) {
                        var thankmsg;
                        if ( selectedslots.length > 1 ) {
                            thankmsg = '<div class="p247thankyou">' + p247_translate.thankyou + '<br>' + p247_translate.reg_plu + '<br>' + p247_translate.emailsent + '</div>';
                            //thankmsg = '<div class="p247thankyou">Thank you!<br>Your selected prayer slots have been registered.<br>We have sent you an email to confirm.</div>';                            
                        }
                        else {
                            thankmsg = '<div class="p247thankyou">' + p247_translate.thankyou + '<br>' + p247_translate.reg_sng + '<br>' + p247_translate.emailsent + '</div>';
                            //thankmsg = '<div class="p247thankyou">Thank you!<br>Your selected prayer slot has been registered.<br>We have sent you an email to confirm.</div>';                                                        
                        }
                        thankmsg = thankmsg + '<div class="p247closebuttondiv">';
                        thankmsg = thankmsg + '<button type="button" class="p247button p247selectbtn" id="p247closebtnid">' + p247_translate.close + '</button>';
                        thankmsg = thankmsg + '</div>';
                        sumform.innerHTML = thankmsg;
                    }
                    
                    // Empty array of selected slots. User can start a new selection if they wish.
                    selectedslots.splice(0, selectedslots.length);
                    for (var si = 0; si < selslots.length; si++) {
                        for (var sj = 0; sj < selslots[si].length; sj++) {
                            selslots[si][sj] = false;
                        }
                    }

                },
                error: function( erdata ) {
                    // Server replied to say that an error occured
                    console.log( 'Returned error from AJAX' );
                    console.log( erdata );
                    // Enable select button, just in case
                    var enb = document.getElementById('p247selectbtnid');
                    if ( enb ) {                
                        enb.disabled = false;                    
                    }
                }
            });
        }
        else {
            // Name and/or email were left blank so simply reneable the Confirm button
            var confb = document.getElementById('p24xconfirmbtnid');
            if ( confb ) {                
                confb.disabled = false;                    
            }

        }

        // Disable select button in anticipation of successful return
        var enb = document.getElementById('p247selectbtnid');
        if ( enb ) {                
            enb.disabled = true;                    
        }
        
        // Stop form from submitting, 
        // return false;
    });
    
    // Event handler for Cancel button
    $(document).on('click','#p247cancelbtnid',function() {
        // Hide this section of the page
        var nodeList = document.querySelectorAll(".p247summary");
        if (nodeList.length) {
            nodeList[0].style.display = 'none';
        }
        // Empty array of seleceted slot descriptions
        selectedslots.splice(0, selectedslots.length);

        // Enable select button
        var enb = document.getElementById('p247selectbtnid');
        if ( enb ) {                
            enb.disabled = false;                    
        }

    });

    // Event handler for Close button on Submit form
    $(document).on('click','#p247closebtnid',function() {
        // Hide this section of the page
        var nodeList = document.querySelectorAll(".p247summary");
        if (nodeList.length) {
            nodeList[0].style.display = 'none';
        }
    });

} );
//   **********************************************
//   END of document.ready
//   **********************************************


/*************************************************************************
*  Function:    validate
*  Parameters:  entered name and entered email address
*  Returns:     True if both fields are not zero length and a valid email 
*               address was supplied
*************************************************************************/
function validate( entname, entemail ) {
    const re = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

    var allok = true;
    if ( !entname.length || !entemail.length ) {
        allok = false;
    }
    else {
        allok = re.test(String(entemail).toLowerCase());
    }
    
    return allok;
}

/*************************************************************************
*  Function:    anyslotsselected
*  Parameters:  2D array of selected flags
*  Returns:     Bool showing at least one hour on any day is selected
*************************************************************************/
function anyselectedslots( selslots ) {
    for (var i = 0; i < selslots.length; i++ ) {
        for (var j = 0; j < selslots[i].length; j++ ) {
            if ( selslots[i][j] ) {
                return true;
            }
        }
    }
    return false;    
}

/*************************************************************************
*  Function:    someslotsselected
*  Parameters:  Int index of selected day, and 2D array of selected flags
*  Returns:     Bool showing at least one hour on this day is selected
*************************************************************************/
function someslotsselected( index, selslots ) {
    for (var j = 0; j < selslots[index].length; j++ ) {
        if ( selslots[index][j] ) {
            return true;
        }
    }
    return false;
}

/*************************************************************************
*  Function:    updatedaystatus
*  Parameters:  Array of status for each day, Array of status for each hour
*  Returns:     None. Sets appropriate daya status given hour slots status
*               in that day
*************************************************************************/
function updatedaystatus( darray, hrecall ) {
    for (var i = 0; i < hrecall.length; i++) {
        var numdisabled = 0;
        var numchosen = 0;
        var numempty = 0
        for (var j = 0; j < hrecall[i].length; j++) {
            if ( hrecall[i][j] == 'disabled' ) {
                numdisabled++;
            }
            if ( hrecall[i][j] == 'empty' ) {
                numempty++;
            }
            if ( hrecall[i][j] == 'chosen' ) {
                numchosen++;
            }
        }
        if ( numdisabled == 24 ) {
            darray[i][0] = 'disabled';
            darray[i][1] = 0.0;
        }
        else if ( numempty == 0 && numchosen == 0 ) {
            darray[i][0] = 'disabled';
            darray[i][1] = 0.0;
        }
        else if ( numempty == 0 ) {
            darray[i][0] = 'chosen';
            darray[i][1] = 1.0;
        }
        else if ( numchosen == 0 ) {
            darray[i][0] = 'empty';
            darray[i][1] = 0.0;
        }
        else {
            // some chosen but not all
            darray[i][0] = 'somechosen';
            darray[i][1] = numempty / (numempty + numchosen);
        }
    }        

}

/*************************************************************************
*  Function:    updatedayspaint
*  Parameters:  Array of day status
*  Returns:     None. Paint all days according to status
*************************************************************************/
function updatedayspaint( darray ) {
    var dgrid = document.getElementById('p24xgridid');
    if ( dgrid ) {
        var dslots = dgrid.getElementsByClassName('p24xgriditem');
        for (var i = 0; i < darray.length; i++) {
            paintthisday( dslots[i], darray[i] );
        }
    }
}

/*************************************************************************
*  Function:    updatedayspaint
*  Parameters:  DOM element for a day, Status array (2 elements) for this day
*  Returns:     None. Paint this day according to status
*************************************************************************/
function paintthisday( obj, thisday ) {
    switch (thisday[0]) {
        case 'selected':
            obj.style.backgroundColor = getbgcolor( 'selected' );                
            obj.style.backgroundImage = 'none';
            break;
        case 'expanded':
            obj.style.backgroundColor = getbgcolor( 'expanded' );                
            obj.style.backgroundImage = 'none';
            break;
        case 'empty':
            obj.style.backgroundColor = getbgcolor( 'empty' );                
            obj.style.backgroundImage = 'none';
            break;
        case 'chosen':
            obj.style.backgroundColor = getbgcolor( 'chosen' );                
            obj.style.backgroundImage = 'none';
            break;
        case 'somechosen':
            // The second array element contains the 'proportion of emptiness'. Set a gradient fill accordingly.
            obj.style.backgroundColor = getbgcolor( 'empty' );  
            var gr = (thisday[1] * 200 - 100).toString();
            var or = (thisday[1] * 200).toString();
            obj.style.backgroundImage = 'linear-gradient(to right, ' + getbgcolor( 'empty' ) + ' ' + gr + '%, ' + getbgcolor( 'chosen' ) + ' ' + or + '%)';                                
            break;
        default:
            obj.style.backgroundColor = getbgcolor( 'disabled' );
            obj.style.backgroundImage = 'none';
    }    
}


/*************************************************************************
*  Function:    painttimegrid (Apply bg color given slots status from server)
*  Parameters:  Int index of selected day, and 2D array of slots status
*               obtained from server
*  Returns:     n/a
*************************************************************************/
function painttimegrid( index, hrecall ) {
    var tgrid = document.getElementById('p24xtimegridid');
    if ( tgrid ) {
        var tslots = tgrid.getElementsByClassName('p24xtimeitem');
        for (var i = 0; i < hrecall[index].length; i++) {
            tslots[i].style.backgroundColor = getbgcolor( hrecall[index][i] );
        }        
    }
}
    
/*************************************************************************
*  Function:    paintselected  (Apply bg color to selected slots)
*  Parameters:  Int index of selected day, and 2D array of selected flags
*  Returns:     n/a
*************************************************************************/
function paintselected( index, selslots ) {
    var tgrid = document.getElementById('p24xtimegridid');
    if ( tgrid ) {
        var tslots = tgrid.getElementsByClassName('p24xtimeitem');
        for (var i = 0; i < selslots[index].length; i++) {
            if ( selslots[index][i] ) {
                tslots[i].style.backgroundColor = getbgcolor('selected');
            }
        }
    }
}

/*************************************************************************
*  Function:    getbgcolor
*  Parameters:  String indicating slot status
*  Returns:     String showing color. Allows colors to be set here only
*************************************************************************/
function getbgcolor( status ) {
    var color;
    switch (status) {
        case 'empty':
//            color = 'palegreen';
            color = p247_vars.colors['empty'];
            break;
        case 'chosen':
//            color = 'orange';
            color = p247_vars.colors['chosen'];
            break;
        case 'selected':
//            color = 'lightblue';
            color = p247_vars.colors['selected'];
            break
        case 'expanded':
//            color = 'lemonchiffon';
            color = p247_vars.colors['expanded'];
            break
        default:
            color = 'transparent';
    }
    return color;
}

/*************************************************************************
*  Function:    removeA  INACTIVE
*  Parameters:  array of objects
*  Returns:     the same array but now emptied of elements
*************************************************************************/
function removeA(arr) {
    var what, a = arguments, L = a.length, ax;
    while (L > 1 && arr.length) {
        what = a[--L];
        while ((ax= arr.indexOf(what)) !== -1) {
            arr.splice(ax, 1);
        }
    }
    return arr;
}

/*************************************************************************
*  Function:    buildtimegrid
*  Parameters:  String containined elelment id of day
*  Returns:     String containing html for all timeslots in day
*************************************************************************/
function buildtimegrid( dayid ) {
    var bgcolor = p247_vars.colors['expanded'];
    var tgrid = '<div class="p24xtimegrid" id="p24xtimegridid" style="background-color:' + bgcolor + ';">';
    var t;
    var tstr;
    for(t = 0; t <=23; t++) {
        if (t<10) {
            tstr = '0' + t.toString() + ':00';
        }
        else {
            tstr = t.toString() + ':00';
        }
        
        tgrid = tgrid + '<div class="p24xtimeitem" id="' + dayid + 'tm' + t.toString() + '">';
        tgrid = tgrid + tstr;
        tgrid = tgrid + '</div>';
    }
    tgrid = tgrid + '</div>';
    
    return tgrid;
}

/*************************************************************************
*  Function:    buildform
*  Parameters:  none
*  Returns:     String containing html for form
*************************************************************************/
function buildform() {
    var formhtml = '<form class="p247_all_forms" id="p247form" method="POST">';
    formhtml = formhtml + '<div class="p247textfields"><div class="p247formitem"><label for="p247name">';
    formhtml = formhtml + p247_translate.name + '</label>';
    formhtml = formhtml + '<input type="text" name="p247name" id="p247name" value="" /></div>';
    formhtml = formhtml + '<div class="p247formitem"><label for="p247email">';
    formhtml = formhtml + p247_translate.email + '</label>';
    formhtml = formhtml + '<input type="text" name="p247email" id="p247email" value="" /></div>';
    formhtml = formhtml + '</div>';
    
    formhtml = formhtml + '<div class="p247formbtns">';
//    formhtml = formhtml + '<input type="submit" class="green p247button" id="p24xconfirmbtnid" value="Confirm" />';
    formhtml = formhtml + '<button type="button" class="p247button p247confirm" id="p247confirmbtnid">' + p247_translate.confirm + '</button>';
    formhtml = formhtml + '<button type="button" class="p247button p247cancel" id="p247cancelbtnid">' + p247_translate.cancel + '</button></div>';
    formhtml = formhtml + '</form>';

    return formhtml;
}

/*************************************************************************
*  Function:    getajaxslotsarray
*  Parameters:  Array of date objects
*  Returns:     Array of date strings for Ajax transfer
*************************************************************************/
function getajaxslotsarray( selected ) {
    var txt = [];
    n = 0;
    
    for (dt of selected) {
        var mth = dt.getMonth() + 1;
        var mthstr = ("00" + mth).slice(-2);
        var dtstr = ("00" + dt.getDate()).slice(-2);
        var hrstr = ("00" + dt.getHours()).slice(-2);
        txt[n] = dt.getFullYear() + '-' + mthstr + '-' + dtstr + ' ' + hrstr + ':00:00';
        n = n + 1;
    }
    return txt;
}

/*************************************************************************
*  Function:    formattimeslottext
*  Parameters:  Array of date objects
*  Returns:     Array of friendly timeslot desciptions to display to user
*  Comment:     Revised to supply locale translation, although format is not quite so nice
*************************************************************************/
function formattimeslottext( selected ) {
    //var days = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
    //var months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    var txt = [];
    n = 0;
    
    // This provides a reasonable assessment of the local language
    const getNavigatorLanguage = () => {
        if (navigator.languages && navigator.languages.length) {
            return navigator.languages[0];
        } 
        else {
            return navigator.userLanguage || navigator.language || navigator.browserLanguage || 'en';
        }
    }
        
    var options = {
        month: "short",
        weekday: "long",
        day: "numeric",
        hour12: true,
        hour: "numeric",
    };
    var eoptions = {
        hour12: true,
        hour: "numeric",
    };
        
    for (dt of selected) {           
        var endtime = new Date();
        endtime.setTime(dt.getTime() + (60*60*1000));

        var stdatest = dt.toLocaleTimeString( getNavigatorLanguage, options );
        var endatest = endtime.toLocaleTimeString( getNavigatorLanguage, eoptions );
        
        txt[n] = stdatest + ' - ' + endatest;
        
 /*       
        var hrs = dt.getHours();
        var ampms = 'am';
        if ( hrs > 12 ) {
            hrs = hrs - 12;
            ampms = 'pm';
        }
        if ( hrs == 12 ) {
            ampms = 'pm';
        }
        if ( hrs == 0 ) {
            hrs = 12;
        }

        var hre = endtime.getHours();
        var ampme = 'am';
        if ( hre > 12 ) {
            hre = hre - 12;
            ampme = 'pm';
        }
        if ( hre == 12 ) {
            ampme = 'pm';
        }
        if ( hre == 0 ) {
            hre = 12;
        }
        
        var date = dt.getDate();
        var dateth = 'th';
        if ( date == 1 || date == 21 ) { dateth = 'st' };
        if ( date == 2 || date == 22 ) { dateth = 'nd' };
        if ( date == 3 || date == 23 ) { dateth = 'rd' };

        txt[n] = days[dt.getDay()] + ' ' + date + dateth + " " + months[dt.getMonth()] + ", " + hrs + ampms + ' to ' + hre + ampme;
*/    
    
        n = n + 1;
    }
    return txt;
}


//******************************************************************
//**********  END OF FILE ******************************************
//******************************************************************
