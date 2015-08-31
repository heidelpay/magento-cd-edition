/*
   Released under the GNU General Public License (Version 2)
   [http://www.gnu.org/licenses/gpl-2.0.html]
 */

//<![CDATA[
var Heidelpay = {} ;

console.log('Heidelpay CD-Edition');

Heidelpay.Registration = Class.create({


	initialize: function() {
		document.observe('dom:loaded', this.register.bind(this));
		Ajax.Responders.register(this);
	},
	register: function () {
		if (!window.review || review.overriddenOnSave || review.overriddenOnComplete) {
			return this;
		}

		var actPayment = $$('input:checked[type=radio][name=\'payment[method]\']')[0].id.replace(/p_method_/,"");
		
		review.overriddenOnComplete = function (transport) {
			 return true;
		}

		review.overriddenOnSave = function (transport) {

			//checkout.setLoadWaiting('payment');
			
			var url  = $(actPayment+'_URL').value;
			
			//console.log('Heidelpay URL '+url);

			var form = new Element('form', {
					style:'display:none',
					action: url ,
					method: "POST"
				});
				$(document.body).insert(form);

				var data = { 
						ACCOUNT_BRAND: $$("." + actPayment + "_ACCOUNT_BRAND")[0].value ,
						ACCOUNT_NUMBER: $$("." + actPayment +"_ACCOUNT_NUMBER")[0].value,
						ACCOUNT_HOLDER: $$("." + actPayment + "_ACCOUNT_HOLDER")[0].value,
						ACCOUNT_EXPIRY_MONTH: $$("." + actPayment + "_ACCOUNT_EXPIRY_MONTH")[0].value,
						ACCOUNT_EXPIRY_YEAR: $$("." + actPayment + "_ACCOUNT_EXPIRY_YEAR")[0].value,
						ACCOUNT_VERIFICATION: $$("." + actPayment + "_ACCOUNT_VERIFICATION")[0].value
				};

				for ( var key in data ) {
					form.insert( 
							{bottom: new Element(
									'input',
									{type: 'text', name: key.replace(/_/,"."), value: data[key] }
							)}
					);

				};

				/*form.insert( 
							{bottom: new Element(
									'input',
									{type: 'submit', name: 'submit', value: 'submit' }
							)}
					);
				*/
				//checkout.setLoadWaiting('payment');
				form.submit();

				return true;

		}

		if ( actPayment == 'hcdcc' || actPayment == 'hcddc' ) {
			var newreg = $$('input:checked[type=radio][name=\''+actPayment+'_use_again\']')[0].value ;
			if ( newreg == 1) {
				review.onSave = review.overriddenOnSave.bind(review);
				review.onComplete = review.overriddenOnComplete.bind(review);
				
			}
		}
	},
	onComplete: function () {
		this.register.defer();
	},


});

new Heidelpay.Registration();


Heidelpay.toggle = Class.create({
	hpform: function ( actPayment, change ) {

		var replace = '';

		$(actPayment + "_hpform").toggle() ;

		if (change == 'false') replace = '***';

		$$("." + actPayment +"_ACCOUNT_NUMBER")[0].value = replace;
		$$("." + actPayment + "_ACCOUNT_VERIFICATION")[0].value = replace;

	},
	button: function (url) {
		

		$$('.btn-hcdmpa').each(Element.toggle);
		
		$$(".btn-checkout").each(Element.toggle);

		$$(".masterpass-please-wait").each(Element.toggle);
		
		window.location.href = url ;
		
	}
});


Heidelpay.toggle.getInstance = function () {
	if (!this.instance) {
		this.instance = new this();
	}
	return this.instance;
};


//]]>