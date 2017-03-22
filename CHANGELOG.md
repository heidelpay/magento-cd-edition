# Release Notes - heidelpay extension for Magento 1

## v17.3.13

### Added
- new payment method invoice secured
- new payment method direct debit secured
- add doc blocks to file and functions  

### Changed
 - enforce code style and magento marketplace standard
 - move response action into a response controller and  code restructured   
#### giropay
- input fields for iban and bic in store front are not longer required
### invoice
 - the magento invoice will now be generated automatically, but is set to not paid

### Fixed


### Removed
 - remove insurance provider option from direct debit and invoice payment method. Please use the new payment methods instead.
 - payment method yapital is not longer available. The model will not be removed because of backwards capability.
 - payment method magir cart is not longer available. The model will not be removed because of backwards capability.