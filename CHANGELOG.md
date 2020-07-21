# Release Notes - heidelpay extension for Magento 1

## v20.07.22
### Added
- Ensure compatibility with Security-Patch SUPEE-11314

### Fixed
- Custom status for processing was not set as expected.
- Direct debit secured: invoice was not sent automatically.
- Prepayment: Payment information did not appear on invoice.

### Changed
- Improve reliability of basket calculation.

## v19.4.16

### Added
- Enabled payment method Santander Invoice
- Added support information to readme.

### Changed
- Updated heidelpay logo.

## v18.8.14

### Added
- Missing annotations.

### Fixed
- Issue which lead to an error when heidelpay invoicing was activated. 
- Issue that causes registration to fail for guest users.
- Issue that lead to error when billsafe was selected.
- Template for debit card was set incorrectly.
- Invoice object was not created on place order.
- Several code style issues.

## v18.6.27

### Added
- new payment method payolution invoice

### Changed
- removed checkbox for automatic invoice creation for prepayment, since it always has to be created.
- disabled Yapital payment option for frontend, since this payment method is deprecated.
- disabled MangirKart payment option for frontend, since this payment method is deprecated.

### Fixed
- fixed several code style issues
- fixed a bug which resulted in a warning when an invoice email is sent due to a missing variable init.

## v18.3.1

### Changed
- Renamed "Heidelpay Payment GmbH" to "heidelpay GmbH" due to re-branding.
- Renamed "SOFORT Überweisung" to "Sofort" due to re-branding.

## v17.6.2

### Fixed
 - pending payment transaction has been handled as processed

## v17.5.17

### Fixed
 - online capture failed because of missing reference id

## v17.3.30

### Added
- new payment method invoice secured
- new payment method direct debit secured
- add doc blocks to file and functions
#### prepayment, invoice and invoice secured
- add payment information to pdf template for invoice and prepayment
#### direct debit and direct debit secured
- add creditor identifier and mandate reference ID to pdf invoice 

### Changed
 - enforce code style and magento marketplace standard
 - move response action into a response controller   
#### giropay
- input fields for iban and bic in store front are not longer required
### invoice
 - the magento invoice will now be generated automatically, but is set to not paid
### Fixed

### Removed
 - remove insurance provider option from direct debit and invoice payment method. Please use the new payment methods instead.
 - payment method yapital is not longer available. The model will not be removed because of backwards capability.
 - payment method MangirKart is not longer available. The model will not be removed because of backwards capability.
 ### direct debit
  -  bic is not longer necessary for the 23 SEPA countries