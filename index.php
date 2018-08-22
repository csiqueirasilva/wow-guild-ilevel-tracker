<?php
	include('secret.php');
	$locale = isset($_REQUEST['locale']) ? $_REQUEST['locale'] : 'en_US';
	$members = isset($_REQUEST['members']) ? explode(',', $_REQUEST['members']) : array();
?>
<!DOCTYPE html>
<html>
	<head>
		<script
		  src="https://code.jquery.com/jquery-3.3.1.min.js"
		  integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8="
		  crossorigin="anonymous"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.0/spectrum.min.js"></script>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/spectrum/1.8.0/spectrum.min.css" type="text/css" />

		<script src="https://cdnjs.cloudflare.com/ajax/libs/jexcel/1.5.0/js/jquery.jexcel.js"></script>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jexcel/1.5.0/css/jquery.jexcel.min.css" type="text/css" />
		<style>
			body, html {
				height: 100%;
				width: 100%;
				margin: 0px;
			}
		
			#app {
				display: none;
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
		</style>		
	</head>
	<body>
		<div id="load"></div>
		
		<div id="app">
			<a id="download" download="progress.json">Download full json</a>
			<div id="excel"></div>
		</div>
		<script>
			var APIKEY = '<?= APIKEY ?>';
			var REALMNAME = '<?= REALMNAME ?>';
			var GUILDNAME = '<?= GUILDNAME ?>';
			var LOCALE = '<?= $locale ?>';
			var LEVELCAP = <?= LEVELCAP ?>;
			
			var toFetch = <?= json_encode($members); ?>;
			var fetching = false;
			var dataList = [];
			var excelData = [];
			var excelHeaders = ['name', 'realm', 'ilevel', 'amulet'];
			
			function loadPage() {
				$('#excel').jexcel({
					data: excelData,
					colHeaders: excelHeaders,
					colWidths: [100, 80]
				});
				
				var url = URL.createObjectURL( new Blob( [JSON.stringify(dataList)], {type:'text/plain'} ) );
				$("#download").attr("href", url);
				
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
				
				$('#excel').jexcel('updateSettings', {
					table: function (instance, cell, col, row, val, id) {
						
						var total = excelData.length;
						var half = parseInt(total / 2);
						var okColor = [166, 255, 98];
						var halfColor = [255, 239, 67];
						var badColor = [255, 68, 58];
						var intRow = parseInt(row); 
						var fraction = (intRow % half) / half;
						
						var c1 = okColor;
						var c2 = halfColor;
						
						if(intRow >= half) {
							c1 = halfColor;
							c2 = badColor;
						}
						
						var color = interpolate(c1, c2, fraction);
						
						$(cell).css('background-color', toHexString(color));
						
						/*// Number formating
						if (col == 3) {
							// Get text
							txt = $(cell).text();
							// Format text
							txt = numeral(txt).format('0,0.00');
							// Update cell value
							$(cell).html(' $ ' + txt);
						}

						// Odd row colours
						if (row % 2) {
							$(cell).css('background-color', '#edf3ff');
						}

						// Remove controls for the last row
						if (row == 9) {
							if (col < 3) {
								$(cell).html('');
							} 

							if (col == 2) {
								$(cell).html('Total');
							}

							$(cell).css('background-color', '#f46e42');
							$(cell).css('color', '#fff');
						}*/
					}
				});
				
				$('#app').show(() => {
					$('#load').remove();
				});
			}
			
			function pushExcelChar(chr) {
				var name = chr['name'];
				var realm = chr['realm'];
				var ilvl = chr['items']['averageItemLevel'];
				var amulet = parseInt(chr['items']['neck']['azeriteItem']['azeriteLevel']);
				amulet += Math.round( ( chr['items']['neck']['azeriteItem']['azeriteExperience'] / (chr['items']['neck']['azeriteItem']['azeriteExperience'] + chr['items']['neck']['azeriteItem']['azeriteExperienceRemaining']) )  * 100) / 100;
				excelData.push([
					name,
					realm,
					ilvl,
					amulet
				]);
			}
			
			<?php if(count($members) === 0): ?>
			$.getJSON('https://us.api.battle.net/wow/guild/' + REALMNAME + '/' + GUILDNAME + '?fields=members&locale=' + LOCALE + '&apikey=' + APIKEY, (data) => {
				data['members'].forEach((el) => {
					if(el['character']['level'] === LEVELCAP) {
						toFetch.push(el['character']['name']);
					}
				});
			<?php endif; ?>
			
				var loadInterval = setInterval((data) => {
					if(!fetching && toFetch.length > 0) {
						fetching = true;
						var charname = toFetch.shift();
						var realm = REALMNAME;
						<?php if(count($members) > 0): ?>
						var buf = charname.split('-');
						realm = buf[0];
						charname = buf[1];
						<?php endif; ?>
						$.getJSON('https://us.api.battle.net/wow/character/' + realm + '/' + charname + '?fields=reputation%2Citems%2Cstatistics&locale=' + LOCALE +'&apikey=' + APIKEY, (chr) => {
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