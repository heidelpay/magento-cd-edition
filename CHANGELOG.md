# Release Notes - heidelpay extension for Magento 1

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