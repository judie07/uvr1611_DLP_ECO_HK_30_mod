var toolbar = {
	date: null,
	init: function()
	{
		this.home = $("#home");
		this.backToChart = $("#backToChart");
		this.back = $("#back");
		this.back1 = $("#back1");
		this.today = $("#date");
		this.forward = $("#forward");
		this.forward1 = $("#forward1");
		this.datepicker = $("#datepicker");
		this.buttonset = $("#buttonset");
		this.period = $("#period");
		this.grouping = $("#grouping");
		
		// home button
		this.home.button({
			icons: {
				primary: "ui-icon-home"
			}
		}).click(function (){
			location.hash = "home";
		});
		
		// back button
		this.back.button({
			icons: {
				primary: "ui-icon-carat-1-w"
			},
			text: false
		}).click(function (){
			toolbar.setDate(new Date(toolbar.date.getTime() - 86400000));
		});
		this.back1.button({
			icons: {
				primary: "ui-icon-carat-1-w"
			},
			text: false
		}).click(function (){
			toolbar.setDate(new Date(toolbar.date.getTime() - 86400000*7));
		});
		// back to chart button
		this.backToChart.button({
			icons: {
				primary: "ui-icon-carat-1-w"
			}
		}).click(function (){
			menu.selectedItem.load();
			toolbar.showDateNavigation();
			toolbar.showPeriod();
			menu.selectedItem.table.getTable().show();
			$("#minmax_chart").hide();
			if(menu.selectedItem.columns.digital.length)
				$("#step_chart").show();
			$("#line_chart").show();
		});
		
		// forward button
		this.forward.button({
			icons: {
				primary: "ui-icon-carat-1-e"
			},
			text: false
		}).click(function (){
			toolbar.setDate(new Date(toolbar.date.getTime() + 86400000));
		});
		this.forward1.button({
			icons: {
				primary: "ui-icon-carat-1-e"
			},
			text: false
		}).click(function (){
			toolbar.setDate(new Date(toolbar.date.getTime() + 86400000*7));
		});
		
		// today button
		this.today.button().click(function (){
			toolbar.setDate(new Date());
		});
		
		// init datepicker
		this.datepicker.addClass("ui-widget ui-widget-content ui-corner-all").datepicker({
			"onSelect": function(selectedDate){
				toolbar.setDate($.datepicker.parseDate("dd.mm.yy",selectedDate));
			}
		});
		//keyboard popup fix mobile
		$.ui.dialog.prototype._focusTabbable = $.noop;
		toolbar.setDate(new Date());
		
		// init buttonsets
		this.buttonset.buttonset();
		this.period.buttonset().change(function(){menu.selectedItem.load()});
		this.grouping.buttonset().change(function(){menu.selectedItem.load()});
		
		// Add event handler when focused on an element with which has datepicker intialised
	    $('.hasDatepicker').on('focus', function() {

		// store the element for use inside the position function
		var $target = $(this);

		// get the elements datepicker widget object and set it's position based on the target element
		$target.datepicker('widget').position({

			my: 'left top',
			at: 'left bottom',
			of: $target
		});
		$target.blur();
	 });
	 $('.hasDatepicker').on('focusOut', function() {
		// focus diagramm
		$('#container').focus();
	 });
	},
	setDate: function(newDate)
	{
		if(newDate.getTime() <= (new Date()).getTime())
		{
			this.date = newDate;
			this.datepicker.datepicker("setDate", this.date);
			this.forward.button("enable");
			this.forward1.button("enable");
			if(menu.selectedItem)
				menu.selectedItem.load();
		}
		if(newDate.getTime() + 86400000 > (new Date()).getTime())
		{
			this.forward.button("disable");
			this.forward1.button("disable");
		}
		setTimeout(function () {
		 $('#datepicker').blur();
		}, 100);
	},
	hideDateNavigation: function()
	{
		this.datepicker.hide();
		this.buttonset.hide();
		this.period.hide();
		this.grouping.hide();
		this.backToChart.hide();
		this.home.show();
	},
	showDateNavigation: function()
	{
		this.home.show();
		this.datepicker.show();
		this.buttonset.show();
		this.backToChart.hide();
	},
	showPeriod: function()
	{
		this.grouping.hide();
		this.period.show();
	},
	showGrouping: function()
	{
		this.period.hide();
		this.grouping.show();
	},
	getPeriod: function()
	{
		return $("#period input[type='radio']:checked").val();
	},
	getGrouping: function()
	{
		return $("#grouping input[type='radio']:checked").val();
	},
	showBackToChart: function()
	{
		this.hideDateNavigation();
		this.home.hide();
		this.backToChart.show();
	}
}

