set_fee(type,typevalue) {
    // console.log(this.selectsPaymentTerm.PaymentTermSelect);
    // console.log(type);
    // console.log(typevalue);
    if (type == 'local') {

        if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly') {
            if (typevalue == 'LeadspeekPlatformFee') {
                this.costagency.local.Weekly.LeadspeekPlatformFee = this.LeadspeekPlatformFee;
            }else if (typevalue == 'LeadspeekCostperlead') {
                this.costagency.local.Weekly.LeadspeekCostperlead = this.LeadspeekCostperlead;
            }else if (typevalue == 'LeadspeekCostperleadAdvanced') {
                this.costagency.local.Weekly.LeadspeekCostperleadAdvanced = this.LeadspeekCostperleadAdvanced;
            }else if (typevalue == 'LeadspeekMinCostMonth') {
                this.costagency.local.Weekly.LeadspeekMinCostMonth = this.LeadspeekMinCostMonth;
            }
        }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly') {
            if (typevalue == 'LeadspeekPlatformFee') {
                this.costagency.local.Monthly.LeadspeekPlatformFee = this.LeadspeekPlatformFee;
            }else if (typevalue == 'LeadspeekCostperlead') {
                this.costagency.local.Monthly.LeadspeekCostperlead = this.LeadspeekCostperlead;
            }else if (typevalue == 'LeadspeekCostperleadAdvanced') {
                this.costagency.local.Monthly.LeadspeekCostperleadAdvanced = this.LeadspeekCostperleadAdvanced;
            }else if (typevalue == 'LeadspeekMinCostMonth') {
                this.costagency.local.Monthly.LeadspeekMinCostMonth = this.LeadspeekMinCostMonth;
            }
        }else if (this.selectsPaymentTerm.PaymentTermSelect == 'One Time') {
            if (typevalue == 'LeadspeekPlatformFee') {
                this.costagency.local.OneTime.LeadspeekPlatformFee = this.LeadspeekPlatformFee;
            }else if (typevalue == 'LeadspeekCostperlead') {
                this.costagency.local.OneTime.LeadspeekCostperlead = this.LeadspeekCostperlead;
            }else if (typevalue == 'LeadspeekCostperleadAdvanced') {
                this.costagency.local.OneTime.LeadspeekCostperleadAdvanced = this.LeadspeekCostperleadAdvanced;
            }else if (typevalue == 'LeadspeekMinCostMonth') {
                this.costagency.local.OneTime.LeadspeekMinCostMonth = this.LeadspeekMinCostMonth;
            }
        }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid') {
            if (typevalue == 'LeadspeekPlatformFee') {
                this.costagency.local.Prepaid.LeadspeekPlatformFee = this.LeadspeekPlatformFee;
            }else if (typevalue == 'LeadspeekCostperlead') {
                this.costagency.local.Prepaid.LeadspeekCostperlead = this.LeadspeekCostperlead;
            }else if (typevalue == 'LeadspeekCostperleadAdvanced') {
                this.costagency.local.Prepaid.LeadspeekCostperleadAdvanced = this.LeadspeekCostperleadAdvanced;
            }else if (typevalue == 'LeadspeekMinCostMonth') {
                this.costagency.local.Prepaid.LeadspeekMinCostMonth = this.LeadspeekMinCostMonth;
            }
        }

    }else if (type == 'locator') {

        if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly') {
            if (typevalue == 'LocatorPlatformFee') {
                this.costagency.locator.Weekly.LocatorPlatformFee = this.LocatorPlatformFee;
            }else if (typevalue == 'LocatorCostperlead') {
                this.costagency.locator.Weekly.LocatorCostperlead = this.LocatorCostperlead;
            }else if (typevalue == 'LocatorMinCostMonth') {
                this.costagency.locator.Weekly.LocatorMinCostMonth = this.LocatorMinCostMonth;
            }
        }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly') {
            if (typevalue == 'LocatorPlatformFee') {
                this.costagency.locator.Monthly.LocatorPlatformFee = this.LocatorPlatformFee;
            }else if (typevalue == 'LocatorCostperlead') {
                this.costagency.locator.Monthly.LocatorCostperlead = this.LocatorCostperlead;
            }else if (typevalue == 'LocatorMinCostMonth') {
                this.costagency.locator.Monthly.LocatorMinCostMonth = this.LocatorMinCostMonth;
            }
        }else if (this.selectsPaymentTerm.PaymentTermSelect == 'One Time') {
            if (typevalue == 'LocatorPlatformFee') {
                this.costagency.locator.OneTime.LocatorPlatformFee = this.LocatorPlatformFee;
            }else if (typevalue == 'LocatorCostperlead') {
                this.costagency.locator.OneTime.LocatorCostperlead = this.LocatorCostperlead;
            }else if (typevalue == 'LocatorMinCostMonth') {
                this.costagency.locator.OneTime.LocatorMinCostMonth = this.LocatorMinCostMonth;
            }
        }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid') {
            if (typevalue == 'LocatorPlatformFee') {
                this.costagency.locator.Prepaid.LocatorPlatformFee = this.LocatorPlatformFee;
            }else if (typevalue == 'LocatorCostperlead') {
                this.costagency.locator.Prepaid.LocatorCostperlead = this.LocatorCostperlead;
            }else if (typevalue == 'LocatorMinCostMonth') {
                this.costagency.locator.Prepaid.LocatorMinCostMonth = this.LocatorMinCostMonth;
            }
        }

    }else if (type == 'locatorlead') {
        if (typevalue == 'FirstName_LastName') {
            this.costagency.locatorlead.FirstName_LastName = this.lead_FirstName_LastName;
        }else if (typevalue == 'FirstName_LastName_MailingAddress') {
            this.costagency.locatorlead.FirstName_LastName_MailingAddress = this.lead_FirstName_LastName_MailingAddress;
        }else if (typevalue == 'FirstName_LastName_MailingAddress_Phone') {
            this.costagency.locatorlead.FirstName_LastName_MailingAddress_Phone = this.lead_FirstName_LastName_MailingAddress_Phone;
            if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly') {
                this.costagency.locator.Weekly.LocatorCostperlead = this.lead_FirstName_LastName_MailingAddress_Phone;
            }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly') {
                this.costagency.locator.Monthly.LocatorCostperlead = this.lead_FirstName_LastName_MailingAddress_Phone;
            }else if (this.selectsPaymentTerm.PaymentTermSelect == 'One Time') {
                this.costagency.locator.OneTime.LocatorCostperlead = this.lead_FirstName_LastName_MailingAddress_Phone;
            }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid') {
                this.costagency.locator.Prepaid.LocatorCostperlead = this.lead_FirstName_LastName_MailingAddress_Phone;
            }
        }
    }else if(type == 'enhance') {
        if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly') {
            if (typevalue == 'EnhancePlatformFee') {
                this.costagency.enhance.Weekly.EnhancePlatformFee = this.EnhancePlatformFee;
            }else if (typevalue == 'EnhanceCostperlead') {
                this.costagency.enhance.Weekly.EnhanceCostperlead = this.EnhanceCostperlead;
            }else if (typevalue == 'EnhanceMinCostMonth') {
            this.costagency.enhance.Weekly.EnhanceMinCostMonth = this.EnhanceMinCostMonth;
            }
        }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly') {
            if (typevalue == 'EnhancePlatformFee') {
                this.costagency.enhance.Monthly.EnhancePlatformFee = this.EnhancePlatformFee;
            }else if (typevalue == 'EnhanceCostperlead') {
                this.costagency.enhance.Monthly.EnhanceCostperlead = this.EnhanceCostperlead;
            }else if (typevalue == 'EnhanceMinCostMonth') {
                this.costagency.enhance.Monthly.EnhanceMinCostMonth = this.EnhanceMinCostMonth;
            }
        }else if (this.selectsPaymentTerm.PaymentTermSelect == 'One Time') {
            if (typevalue == 'EnhancePlatformFee') {
                this.costagency.enhance.OneTime.EnhancePlatformFee = this.EnhancePlatformFee;
            }else if (typevalue == 'EnhanceCostperlead') {
                this.costagency.enhance.OneTime.EnhanceCostperlead = this.EnhanceCostperlead;
            }else if (typevalue == 'EnhanceMinCostMonth') {
                this.costagency.enhance.OneTime.EnhanceMinCostMonth = this.EnhanceMinCostMonth;
            }
        }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid') {
            if (typevalue == 'EnhancePlatformFee') {
                this.costagency.enhance.Prepaid.EnhancePlatformFee = this.EnhancePlatformFee;
            }else if (typevalue == 'EnhanceCostperlead') {
                this.costagency.enhance.Prepaid.EnhanceCostperlead = this.EnhanceCostperlead;
            }else if (typevalue == 'EnhanceMinCostMonth') {
                this.costagency.enhance.Prepaid.EnhanceMinCostMonth = this.EnhanceMinCostMonth;
            }
        }
    }else if(type == 'b2b') {
        if (this.selectsPaymentTerm.PaymentTermSelect == 'Weekly') {
            if (typevalue == 'B2bPlatformFee') {
                this.costagency.b2b.Weekly.B2bPlatformFee = this.B2bPlatformFee;
            }else if (typevalue == 'B2bCostperlead') {
                this.costagency.b2b.Weekly.B2bCostperlead = this.B2bCostperlead;
            }else if (typevalue == 'B2bMinCostMonth') {
                this.costagency.b2b.Weekly.B2bMinCostMonth = this.B2bMinCostMonth;
            }
        }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Monthly') {
            if (typevalue == 'B2bPlatformFee') {
                this.costagency.b2b.Monthly.B2bPlatformFee = this.B2bPlatformFee;
            }else if (typevalue == 'B2bCostperlead') {
                this.costagency.b2b.Monthly.B2bCostperlead = this.B2bCostperlead;
            }else if (typevalue == 'B2bMinCostMonth') {
                this.costagency.b2b.Monthly.B2bMinCostMonth = this.B2bMinCostMonth;
            }
        }else if (this.selectsPaymentTerm.PaymentTermSelect == 'One Time') {
            if (typevalue == 'B2bPlatformFee') {
                this.costagency.b2b.OneTime.B2bPlatformFee = this.B2bPlatformFee;
            }else if (typevalue == 'B2bCostperlead') {
                this.costagency.b2b.OneTime.B2bCostperlead = this.B2bCostperlead;
            }else if (typevalue == 'B2bMinCostMonth') {
                this.costagency.b2b.OneTime.B2bMinCostMonth = this.B2bMinCostMonth;
            }
        }else if (this.selectsPaymentTerm.PaymentTermSelect == 'Prepaid') {
            if (typevalue == 'B2bPlatformFee') {
                this.costagency.b2b.Prepaid.B2bPlatformFee = this.B2bPlatformFee;
            }else if (typevalue == 'B2bCostperlead') {
                this.costagency.b2b.Prepaid.B2bCostperlead = this.B2bCostperlead;
            }else if (typevalue == 'B2bMinCostMonth') {
                this.costagency.b2b.Prepaid.B2bMinCostMonth = this.B2bMinCostMonth;
            }
        }
    }else if(type == 'simplifi') {
        if (typevalue == 'SimplifiMaxBid') {
            this.costagency.simplifi.Prepaid.SimplifiMaxBid = this.SimplifiMaxBid;
            this.validateMinimumInput('keyup', 'maxBid');
        } else if (typevalue == 'SimplifiDailyBudget') {
            this.costagency.simplifi.Prepaid.SimplifiDailyBudget = this.SimplifiDailyBudget;
            this.validateMinimumInput('keyup', 'dailyBudget');
        } else if (typevalue == 'SimplifiAgencyMarkup') {
            this.costagency.simplifi.Prepaid.SimplifiAgencyMarkup = this.SimplifiAgencyMarkup;
        }
    }else if(type == 'clean') {
        if (typevalue == 'CleanCostperlead' && this.costagency && this.costagency.clean && typeof(this.costagency.clean.CleanCostperlead) !== 'undefined' && this.$global.systemUser) {
            this.costagency.clean.CleanCostperlead = this.CleanCostperlead;
        } else if (typevalue == 'CleanCostperleadAdvanced' && this.costagency && this.costagency.clean && typeof(this.costagency.clean.CleanCostperleadAdvanced) != 'undefined' && this.$global.systemUser) {
            this.costagency.clean.CleanCostperleadAdvanced = this.CleanCostperleadAdvanced;
        }
    }
},