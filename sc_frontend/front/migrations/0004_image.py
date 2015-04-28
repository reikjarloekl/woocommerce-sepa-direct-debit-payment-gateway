# -*- coding: utf-8 -*-
from __future__ import unicode_literals

from django.db import models, migrations


class Migration(migrations.Migration):

    dependencies = [
        ('front', '0003_emailaddress_name'),
    ]

    operations = [
        migrations.CreateModel(
            name='Image',
            fields=[
                ('id', models.AutoField(verbose_name='ID', serialize=False, auto_created=True, primary_key=True)),
                ('received', models.DateTimeField()),
                ('camera', models.ForeignKey(to='front.Camera')),
            ],
            options={
            },
            bases=(models.Model,),
        ),
    ]
