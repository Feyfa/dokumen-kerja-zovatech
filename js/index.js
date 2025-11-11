const data = {
    valid: 1,
    invalid: 2,
    catch_all: 3,
    unknown: 4,
    spamtrap: 5,
    abuse: 6,
    do_not_mail: 7,
    total: 8
};

Object.entries(data).forEach(([key, value]) => {
    console.log(key, value);
});