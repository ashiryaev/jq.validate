(function($){

var validation = {
	defaults: {
		onsubmit: false
	}
}

$.extend($.fn, {
	validate: function(settings) {
		var formValidator = new $.formValidator(settings, this);
		//save validator data
		this.data('validator', formValidator);
		//form handlers
		var options = $.extend({}, validation.defaults, settings);
		this.data('options', options);

		if (options.onsubmit) {
			this.submit(function() {
				var options = $(this).data('options');
				if ($(this).validateForm()) {
					if (options.submitHandler) {
						return options.submitHandler.call(this, $(this).data('validator'));
					}
					return true;
				}	
				return false;
			});
		}
	},
	validateForm: function() {
		var validator = this.data('validator');
		return validator.form();
	}
});

// constructor for validator
$.formValidator = function(settings, form) {
	this.settings = $.extend({}, $.formValidator.defaults, settings);
	this.currentForm = form;
	this.init();
};

$.extend($.formValidator, {
	defaults: {
		errorClass: 'jqValidateError',
		errorElement: 'div',
		fields: {}
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
		//maxlength: $.format("Please enter no more than {0} characters."),
		//minlength: $.format("Please enter at least {0} characters."),
		//rangelength: $.format("Please enter a value between {0} and {1} characters long."),
		//range: $.format("Please enter a value between {0} and {1}."),
		//max: $.format("Please enter a value less than or equal to {0}."),
		//min: $.format("Please enter a value greater than or equal to {0}.")
	},
	prototype: {
		optional: function(element) {
			return !$.formValidator.methods.required.call(this, $.trim(element.value), element) && "dependency-mismatch";
		},
		init: function() {

		},
		form: function() {
			var fields = this.settings.fields;
			var error = false;
			this.prepareForm();
			var validator = this;
			$.each(fields, function(fieldName, field){
				if(!validator.checkField(fieldName, field))
					error = true;
			});
			return error;
		},
		checkField: function(fieldName, settings) {
			var rules = settings.rules;
			if (settings.type == 'multi') {
				fieldName = fieldName+'\[\]';
			}
			var elements = this.findByName(fieldName);
			var error = false;
			var method = '';
			for (var i=0;i<elements.length;i++) {
				for (method in rules) {
					if (!$.formValidator.methods[method].call(this, $(elements[i]).val(), elements[i], rules[method])) {
						error = true;
						this.showError($(elements[i]), method);
						break;
					}
				}
			}
			return error;
		},
		showError: function(element, error) {
			var fieldName = element.attr('name');
			var fieldNameParts = fieldName.match(/^(.+)\[\s*\]$/i);
			if (fieldNameParts) {
				fieldName = fieldNameParts[1];
			}
			//console.log(fieldNameParts);
			var field = this.settings.fields[fieldName];
			var message = '';
			if (field.messages && field.messages[error]) {
				message = field.messages[error];
			}
			else {
				message = $.formValidator.messages[error];
			}

			var label = $("<" + this.settings.errorElement + "/>")
					.addClass(this.settings.errorClass)
					.html(message);
			var options = this.currentForm.data('options');
			options.errorPlacement?options.errorPlacement.call(this, label, element):label.insertAfter(element);
		},
		prepareForm: function() {
			this.hideErrors();
		},
		hideErrors: function() {
			$('.'+this.settings.errorClass).remove()
		},
		checkable: function( element ) {
			return /radio|checkbox/i.test(element.type);
		},
		getLength: function(value, element) {
			switch( element.nodeName.toLowerCase() ) {
			case 'select':
				return $("option:selected", element).length;
			case 'input':
				if( this.checkable( element) )
					return this.findByName(element.name).filter(':checked').length;
			}
			return value.length;
		},
		findByName: function(name) {
			var form = this.currentForm;
			return form.find('[name='+name+']');
		}
	},
	methods: {
		required: function(value, element, param) {
			switch(element.nodeName.toLowerCase()) {
				case 'select':
					var options = $("option:selected", element);
					return options.length > 0 && ( element.type == "select-multiple" || ($.browser.msie && !(options[0].attributes['value'].specified) ? options[0].text : options[0].value).length > 0);
				case 'input':
					if ( this.checkable(element) )
						return this.getLength(value, element) > 0;
				default:
					return $.trim(value).length > 0;
			}
		},

		minlength: function(value, element, param) {
			return this.optional(element) || this.getLength($.trim(value), element) >= param;
		},

		maxlength: function(value, element, param) {
			return this.optional(element) || this.getLength($.trim(value), element) <= param;
		},

		rangelength: function(value, element, param) {
			var length = this.getLength($.trim(value), element);
			return this.optional(element) || ( length >= param[0] && length <= param[1] );
		},

		min: function( value, element, param ) {
			return this.optional(element) || value >= param;
		},

		max: function( value, element, param ) {
			return this.optional(element) || value <= param;
		},

		range: function( value, element, param ) {
			return this.optional(element) || ( value >= param[0] && value <= param[1] );
		},

		email: function(value, element) {
			// contributed by Scott Gonzalez: http://projects.scottsplayground.com/email_address_validation/
			return this.optional(element) || /^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i.test(value);
		},

		url: function(value, element) {
			// contributed by Scott Gonzalez: http://projects.scottsplayground.com/iri/
			return this.optional(element) || /^(https?|ftp):\/\/(((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:)*@)?(((\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5])\.(\d|[1-9]\d|1\d\d|2[0-4]\d|25[0-5]))|((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?)(:\d*)?)(\/((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)+(\/(([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)*)*)?)?(\?((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)|[\uE000-\uF8FF]|\/|\?)*)?(\#((([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(%[\da-f]{2})|[!\$&'\(\)\*\+,;=]|:|@)|\/|\?)*)?$/i.test(value);
		},

		date: function(value, element) {
			return this.optional(element) || !/Invalid|NaN/.test(new Date(value));
		},

		dateISO: function(value, element) {
			return this.optional(element) || /^\d{4}[\/-]\d{1,2}[\/-]\d{1,2}$/.test(value);
		},

		dateDE: function(value, element) {
			return this.optional(element) || /^\d\d?\.\d\d?\.\d\d\d?\d?$/.test(value);
		},

		number: function(value, element) {
			return this.optional(element) || /^-?(?:\d+|\d{1,3}(?:,\d{3})+)(?:\.\d+)?$/.test(value);
		},

		numberDE: function(value, element) {
			return this.optional(element) || /^-?(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d+)?$/.test(value);
		},

		digits: function(value, element) {
			return this.optional(element) || /^\d+$/.test(value);
		},

		creditcard: function(value, element) {
			if ( this.optional(element) )
				return "dependency-mismatch";
			// accept only digits and dashes
			if (/[^0-9-]+/.test(value))
				return false;
			var nCheck = 0,
				nDigit = 0,
				bEven = false;

			value = value.replace(/\D/g, "");

			for (n = value.length - 1; n >= 0; n--) {
				var cDigit = value.charAt(n);
				var nDigit = parseInt(cDigit, 10);
				if (bEven) {
					if ((nDigit *= 2) > 9)
						nDigit -= 9;
				}
				nCheck += nDigit;
				bEven = !bEven;
			}

			return (nCheck % 10) == 0;
		},

		accept: function(value, element, param) {
			param = typeof param == "string" ? param : "png|jpe?g|gif";
			return this.optional(element) || value.match(new RegExp(".(" + param + ")$", "i"));
		},

		equalTo: function(value, element, param) {
			return value == $(param).val();
		}

	}
	
})

})(jQuery)

