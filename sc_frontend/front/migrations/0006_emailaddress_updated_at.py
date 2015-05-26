# -*- coding: utf-8 -*-
from __future__ import unicode_literals

from django.db import models, migrations
import datetime
from django.utils.timezone import utc


class Migration(migrations.Migration):

    dependencies = [
        ('front', '0005_emailaddress_verified'),
    ]

    operations = [
        migrations.AddField(
            model_name='emailaddress',
            name='updated_at',
            field=models.DateTimeField(default=datetime.datetime(2015, 5, 26, 20, 21, 31, 632928, tzinfo=utc), auto_now=True),
            preserve_default=False,
        ),
    ]
