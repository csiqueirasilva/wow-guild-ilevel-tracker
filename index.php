<?php
	include('secret.php');
	$locale = isset($_REQUEST['locale']) ? $_REQUEST['locale'] : 'en_US';
	$members = isset($_REQUEST['guild']) ? array() : (isset($_REQUEST['members']) ? explode(',', $_REQUEST['members']) : $MEMBERS_SRC);
	$guildname = isset($_REQUEST['guild']) ? $_REQUEST['guild'] : GUILDNAME;
	$realmname = isset($_REQUEST['realm']) ? $_REQUEST['realm'] : REALMNAME;
?>
<!DOCTYPE html>
<html>
	<head>
		<script
		  src="https://code.jquery.com/jquery-3.3.1.min.js"
		  integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
		  crossorigin="anonymous"></script>
		  
		<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
		
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>  

		<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs4/dt-1.10.18/cr-1.5.0/datatables.min.css"/>
 
		<script type="text/javascript" src="https://cdn.datatables.net/v/bs4/dt-1.10.18/cr-1.5.0/datatables.min.js"></script>
		
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
		
		<style>
			body, html {
				height: 100%;
				width: 100%;
				margin: 0px;
			}
		
			#app {
				display: none;
				margin: 10px;
				width: calc(100% - 20px);
			}
			
			#load {
				padding: 10px;
				position: absolute;
				top: 0px;
				left: 0px;
				z-index: 999999;
				background-color: rgb(0,0,0);
				color: #fff;
				height: calc(100% - 20px);
				width: calc(100% - 20px);
			}
			
			#download {
				margin-top: 10px;
				margin-bottom: 10px;
			}
			
			table.dataTable thead .sorting:before, table.dataTable thead .sorting:after, table.dataTable thead .sorting_asc:before, table.dataTable thead .sorting_asc:after, table.dataTable thead .sorting_desc:before, table.dataTable thead .sorting_desc:after, table.dataTable thead .sorting_asc_disabled:before, table.dataTable thead .sorting_asc_disabled:after, table.dataTable thead .sorting_desc_disabled:before, table.dataTable thead .sorting_desc_disabled:after {
				bottom: 0em;
			}
			
			.jlink {
				color: #00ccaa;
				line-height: 1em;
			}
			
			.jlink:hover {
				cursor: pointer;
				text-decoration: underline;
			}
			
			#excel tr:hover td {
				background-color: #000;
				color: #fff;
			}
			
			#excel {
				border-collapse: collapse !important;
			}
			
			#excel tbody {
				border-top: 1px black solid;
				border-left: 1px black solid;
				text-align: center;
			}
			
			#excel tr td {
				border-right: 1px black solid;
				border-bottom: 1px black solid;
			}
			
			.moreinfo {
				cursor: default;
			}
			
		</style>		
	</head>
	<body>
		<div id="load"></div>
		
		<div id="app">
			<div>Toggle columns:</div>
			<div id="toggle-columns"></div>
			<table id="excel" width="100%"></table>
		</div>
		<script>
			var APIKEY = '<?= APIKEY ?>';
			var REALMNAME = '<?= $realmname ?>';
			var GUILDNAME = '<?= $guildname ?>';
			var LOCALE = '<?= $locale ?>';
			var LEVELCAP = <?= LEVELCAP ?>;
			
			var toFetch = <?= json_encode($members); ?>;
			var fetching = false;
			var dataList = [];
			var excelData = [];
			var excelHeaders = ['realm', 'name', 'ilevel', 'amulet', 'm0', 'audit'];
			
			function getLastReset() {
				var d = new Date();
				if(d.getUTCDay() < 2 || (d.getUTCDay() === 2 && d.getUTCHours() < 12)) {
					d.setUTCDate(d.getUTCDate() - 7);
				}
				d.setUTCMilliseconds(0); d.setUTCSeconds(0); d.setUTCMinutes(0); d.setUTCHours(15); d.setUTCDate(d.getUTCDate() - (d.getUTCDay() + 5) % 7);
				return new Date(d.getTime());
			}
			
			function toHexString(color) {
				return "#" + ((1 << 24) + (color[0] << 16) + (color[1] << 8) + color[2]).toString(16).slice(1);
			}
			
			function interpolate(c1, c2, fraction) {
				var ret = [];
				for(var i = 0; i < 3; i++) {
					ret[i] = Math.max(0, parseInt((c2[i] - c1[i]) * fraction + c1[i]));
				}
				return ret;
			}
			
			function loadPage() {
				
				var headers = [];
				
				for(var i = 0; i < excelHeaders.length; i++) {
					headers[i] = {title: excelHeaders[i]};
					$('#toggle-columns').append('<a class="jlink"  data-column="' + i + '">' + excelHeaders[i] + '</a>&nbsp;');
				}
				
				var table = $('#excel').DataTable({
					data: excelData,
					columns: headers,
					paging: false,
					order: [[2, 'desc'], [3, 'desc']],
					createdRow: (row, data, dataIndex) => {
						var total = excelData.length;
						var half = parseInt(total / 2);
						var okColor = [166, 255, 98];
						var halfColor = [255, 239, 67];
						var badColor = [255, 68, 58];
						var intRow = parseInt(dataIndex); 
						var fraction = (intRow % half) / half;
						
						var c1 = okColor;
						var c2 = halfColor;
						
						if(intRow >= half) {
							c1 = halfColor;
							c2 = badColor;
						}
						
						var color;

						if(intRow === total - 1) {
							color = badColor;
						} else {
							color = interpolate(c1, c2, fraction);
						}
						
						$(row).css('background-color', toHexString(color));
					},
					columnDefs: [{
						targets: [0, 5],
						visible: false
					},
					{
						targets: [4, 5],
						createdCell: (cell, cellData, rowData, rowIndex, colIndex) => {	
							var lastReset = getLastReset();
							if(colIndex === 4) /* m0 data */ {
								var dungeonStats = dataList[cellData].statistics.subCategories[5].subCategories[7].statistics;
								var check = [
									12785, // waymanor crest
									12782, // tol dagor
									12745, // underrot
									12779, // motherlode
									12776, // temple of sethraliss
									12773, // siege of boralus
									12768, // shrine of the storm
									12763, // kings rest
									12752, // freehold
									12749  // atal dazor
								];
								var missingNames = [];
								var doneNames = [];
								var extractRegex = /(?:\(Mythic )([^)]+)/;
								for(var i = 0; i < dungeonStats.length; i++) {
									var dun = dungeonStats[i];
									if(check.indexOf(dun.id) !== -1) {
										var name = dun.name;
										var match = dun.name.match(extractRegex);
										if(match !== null && match.length > 1) {
											name = match[1];
										}
										if(dun.lastUpdated > lastReset) {
											doneNames.push(name);
										} else {
											missingNames.push(name);
										}
									}
								}
								
								var htmlNames = '<div style=\'margin-bottom: 2px; color:#00ff00;font-weight:bold;\'>DONE</div>';
								
								if(doneNames.length === 0) {
									htmlNames = '';
								} else {
									for(var i = 0; i < doneNames.length; i++) {
										htmlNames += "<div style='color:#fff';>" + doneNames[i] + "</div>";
									}
								}
								
								if(missingNames.length !== 0) {
									htmlNames += '<div style=\'margin-bottom: 2px; color:#ff0000;font-weight:bold;\'>NOT DONE</div>';
									
									for(var i = 0; i < missingNames.length; i++) {
										htmlNames += "<div style='color:#fff';>" + missingNames[i] + "</div>";
									}
								}
								
								rowData[colIndex] = doneNames.length;
								
								$(cell).html(doneNames.length + '/' + check.length);
								$(cell).addClass('moreinfo');
								$(cell).attr('data-html', 'true');
								$(cell).attr('data-toggle', 'tooltip');
								$(cell).attr('title', htmlNames);
							}
						}
					}],
					initComplete: () => {
						$('[data-toggle="tooltip"]').tooltip();
					}					
				});
				
				$('#toggle-columns a').on( 'click', function (e) {
					e.preventDefault();
					var column = table.column( $(this).attr('data-column') );
					column.visible( ! column.visible() );
				} );
				
				$('#app').show(() => {
					$('#load').remove();
				});
			}
			
			function pushExcelChar(chr) {
				var name = chr['name'];
				var realm = chr['realm'];
				var ilvl = chr['items']['averageItemLevel'];
				var amulet = parseInt(chr['items']['neck']['azeriteItem']['azeriteLevel']);
				amulet += Math.min(0.99, Math.round( ( chr['items']['neck']['azeriteItem']['azeriteExperience'] / chr['items']['neck']['azeriteItem']['azeriteExperienceRemaining'] )  * 100 ) / 100);
				excelData.push([
					realm,
					name,
					ilvl,
					amulet,
					dataList.length /* inserts at the end */,
					dataList.length /* inserts at the end */
				]);
			}
			
			<?php if(count($members) === 0): ?>
			$.getJSON('https://us.api.battle.net/wow/guild/' + REALMNAME + '/' + GUILDNAME + '?fields=members&locale=' + LOCALE + '&apikey=' + APIKEY, (data) => {
				data['members'].forEach((el) => {
					if(el['character']['level'] === LEVELCAP) {
						toFetch.push(el['character']['realm'] + '-' + el['character']['name']);
					}
				});
			<?php endif; ?>
			
				var loadInterval = setInterval((data) => {
					if(!fetching && toFetch.length > 0) {
						fetching = true;
						var fullname = toFetch.shift();
						var buf = fullname.split('-');
						var realm = buf[0];
						var charname = buf[1];
						$.getJSON('https://us.api.battle.net/wow/character/' + realm + '/' + charname + '?fields=audit%2Creputation%2Citems%2Cstatistics&locale=' + LOCALE +'&apikey=' + APIKEY, (chr) => {
							$('#load').append(chr['realm'] + '-' + charname + ',');
							pushExcelChar(chr);
							dataList.push(chr);
							fetching = false;
						});
					} else if (toFetch.length === 0) {
						clearInterval(loadInterval);
						console.log(dataList);
						excelData = excelData.sort(function Comparator(a, b) {
						   if (a[2] < b[2]) return 1;
						   if (a[2] > b[2]) return -1;
						   if (a[3] < b[3]) return 1;
						   if (a[3] > b[3]) return -1;
						   return 0;
						});
						loadPage();
					}
				}, 100);
				
			<?php if(count($members) === 0): ?>
			});
			<?php endif; ?>
			
		</script>
	</body>
</html>