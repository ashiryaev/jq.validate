(function($){

var validation = {
	defaults: {
		errorClass: 'error',
		errorElement: 'label',
		onsubmit: true
	}
}

$.extend($.fn, {
	validate: function(settings) {
		//var formValidator = new $.formValidator(settings, this);
		//save validator data
		//this.data('validator', formValidator);
		//form handlers
		var options = $.extend({}, validation.defaults, settings);
		if (options.onsubmit) {
			this.submit(function() {
				if ($(this).form()) {
					if ( options.submitHandler ) {
						return options.submitHandler.call( null, validator.currentForm );
					}
					return true;
				}	
				return false;
			});
		}
	},
	validateForm: function() {
		
	}
});

// constructor for validator
$.formValidator = function( options, form ) {
	this.settings = $.extend($.formValidator.defaults, options );
	this.currentForm = form;
	this.init();
};

$.extent($.formValidator, {
	defaults: {
		fields: {},
		errorClass: "error",
		errorElement: "label"
	},
	setDefaults: function(settings) {
		$.extend( $.formValidator.defaults, settings );
	},
	messages: {
		required: "This field is required.",
		remote: "Please fix this field.",
		email: "Please enter a valid email address.",
		url: "Please enter a valid URL.",
		date: "Please enter a valid date.",
		number: "Please enter a valid number.",
		digits: "Please enter only digits",
		creditcard: "Please enter a valid credit card number.",
		equalTo: "Please enter the same value again.",
		accept: "Please enter a value with a valid extension.",
		maxlength: $.format("Please enter no more than {0} characters."),
		minlength: $.format("Please enter at least {0} characters."),
		rangelength: $.format("Please enter a value between {0} and {1} characters long."),
		range: $.format("Please enter a value between {0} and {1}."),
		max: $.format("Please enter a value less than or equal to {0}."),
		min: $.format("Please enter a value greater than or equal to {0}.")
	},
	prototype: {
		init: function() {
			var fields = this.settings.fields;
		},
		form: function() {
			
		}
	}
	
})

})(jQuery)

