# Short instructions

## `m-to-eml.php` - convert one `.m` file to MIME message

Read one `.m` file from first parameter of from STDIN and output MIME version of it to STDOUT.

## `mbox-feeder.php` - convert multiple messages to UNIX mailbox format

Run `mbox-feeder.php --help` for details. The following information MAY be outdated.

```
Usage:
    mbox-feeder [-m] [-o FILENAME] [--] [DIRECTORY]
    mbox-feeder [-m] [-s] [--] [DIRECTORY]
    (unimplemented) mbox-feeder [-m] [-s -o FILENAME] [--] [DIRECTORY]

By default, the DIRECTORY is the current working directory, the output goes
to STDOUT and no deletion is performed.

-o FILENAME
        write all mail into the specified FILENAME instead of STDOUT.

-m      move files. The cache files that have been succesfully read will be
        removed.

-s      sort mail. The mail will be delivered to each RecipientId's personal
        mailbox in the DIRECTORY.
        (unimplemented) If -o is also specified, FILENAME will be used
        instead of DIRECTORY as base name for destination mailboxes.
        The resulting mailbox names will be formed as simple concatenation
        of FILENAME and RecipientId.
```
